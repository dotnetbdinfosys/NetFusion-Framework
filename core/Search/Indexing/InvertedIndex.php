<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Search\Indexing;

use NetFusion\Core\Search\Contracts\IndexInterface;
use NetFusion\Core\Search\Contracts\TokenizerInterface;
use NetFusion\Core\Search\Contracts\StemmerInterface;
use NetFusion\Core\Search\Contracts\RerankerInterface;
use NetFusion\Core\Search\Analysis\StandardTokenizer;
use NetFusion\Core\Search\Analysis\PorterStemmer;
use NetFusion\Core\Search\Analysis\StopwordFilter;
use NetFusion\Core\Search\Analysis\SynonymFilter;
use NetFusion\Core\Search\Models\Document;
use NetFusion\Core\Search\Models\SearchQuery;
use NetFusion\Core\Search\Models\SearchResult;
use NetFusion\Core\Search\Models\FacetResult;
use NetFusion\Core\Search\Scoring\BM25Scorer;
use NetFusion\Core\Search\Scoring\HybridReranker;
use NetFusion\Core\Search\Faceting\FacetEngine;

class InvertedIndex implements IndexInterface
{
    /**
     * @var array<string, Document> DocId => Document
     */
    protected array $documents = [];

    /**
     * @var array<string, array<string, array<string, PositionalPosting>>> Term => [DocId => [Field => PositionalPosting]]
     */
    protected array $postings = [];

    /**
     * @var array<string, int> DocId => Total Tokens Count
     */
    protected array $docLengths = [];

    protected int $totalTokens = 0;

    protected TokenizerInterface $tokenizer;
    protected ?StemmerInterface $stemmer;
    protected StopwordFilter $stopwordFilter;
    protected SynonymFilter $synonymFilter;
    protected BM25Scorer $bm25Scorer;
    protected RerankerInterface $reranker;
    protected FacetEngine $facetEngine;

    /**
     * @param string $name Index name
     * @param array<string, mixed> $options
     */
    public function __construct(
        protected string $name,
        protected array $options = []
    ) {
        $this->tokenizer = $options['tokenizer'] ?? new StandardTokenizer();
        $this->stemmer = $options['stemmer'] ?? (isset($options['stemming']) && !$options['stemming'] ? null : new PorterStemmer());
        $this->stopwordFilter = $options['stopword_filter'] ?? new StopwordFilter();
        $this->synonymFilter = $options['synonym_filter'] ?? new SynonymFilter($options['synonyms'] ?? []);
        $this->bm25Scorer = new BM25Scorer(
            k1: (float)($options['bm25_k1'] ?? 1.2),
            b: (float)($options['bm25_b'] ?? 0.75)
        );
        $this->reranker = $options['reranker'] ?? new HybridReranker();
        $this->facetEngine = new FacetEngine();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addDocument(Document $doc): static
    {
        $docId = $doc->getId();
        if (isset($this->documents[$docId])) {
            $this->deleteDocument($docId);
        }

        $this->documents[$docId] = $doc;
        $docTokenCount = 0;

        foreach ($doc->getFields() as $field => $value) {
            $text = is_string($value) ? $value : (is_array($value) ? implode(' ', array_filter($value, 'is_string')) : (string)$value);
            if (trim($text) === '') {
                continue;
            }

            $tokensWithPos = $this->tokenizer->tokenizeWithPositions($text);

            foreach ($tokensWithPos as $item) {
                $rawToken = $item['token'];
                if ($this->stopwordFilter->isStopword($rawToken)) {
                    continue;
                }

                $term = $this->stemmer ? $this->stemmer->stem($rawToken) : $rawToken;
                $pos = (int)$item['position'];

                if (!isset($this->postings[$term][$docId][$field])) {
                    $this->postings[$term][$docId][$field] = new PositionalPosting($docId, $field, 0, []);
                }

                $this->postings[$term][$docId][$field]->addPosition($pos);
                $docTokenCount++;
            }
        }

        $this->docLengths[$docId] = max(1, $docTokenCount);
        $this->totalTokens += $docTokenCount;

        return $this;
    }

    public function addDocuments(array $docs): static
    {
        foreach ($docs as $doc) {
            $this->addDocument($doc);
        }
        return $this;
    }

    public function getDocument(string $id): ?Document
    {
        return $this->documents[$id] ?? null;
    }

    public function hasDocument(string $id): bool
    {
        return isset($this->documents[$id]);
    }

    public function deleteDocument(string $id): bool
    {
        if (!isset($this->documents[$id])) {
            return false;
        }

        unset($this->documents[$id]);
        $length = $this->docLengths[$id] ?? 0;
        unset($this->docLengths[$id]);
        $this->totalTokens = max(0, $this->totalTokens - $length);

        // Remove from postings
        foreach ($this->postings as $term => $docMap) {
            if (isset($docMap[$id])) {
                unset($this->postings[$term][$id]);
                if (empty($this->postings[$term])) {
                    unset($this->postings[$term]);
                }
            }
        }

        return true;
    }

    public function count(): int
    {
        return count($this->documents);
    }

    public function flush(): void
    {
        $this->documents = [];
        $this->postings = [];
        $this->docLengths = [];
        $this->totalTokens = 0;
    }

    public function getAverageDocumentLength(): float
    {
        $totalDocs = count($this->documents);
        if ($totalDocs === 0) {
            return 1.0;
        }
        return (float)($this->totalTokens / $totalDocs);
    }

    public function getDocumentFrequency(string $term): int
    {
        return isset($this->postings[$term]) ? count($this->postings[$term]) : 0;
    }

    public function getPostings(): array
    {
        return $this->postings;
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function search(SearchQuery $query): SearchResult
    {
        $startTime = microtime(true);
        $queryString = trim($query->getQueryString());
        $vector = $query->getVector();
        $totalDocs = count($this->documents);

        if ($totalDocs === 0) {
            return SearchResult::empty(round((microtime(true) - $startTime) * 1000, 2));
        }

        $avgDocLength = $this->getAverageDocumentLength();

        // 1. Tokenize and expand query terms
        $rawTokens = $this->tokenizer->tokenize($queryString);
        $filteredTokens = $this->stopwordFilter->filter($rawTokens);
        $expandedTokens = $this->synonymFilter->expand($filteredTokens);

        $stemmedQueryTerms = [];
        foreach ($expandedTokens as $t) {
            $stemmed = $this->stemmer ? $this->stemmer->stem($t) : $t;
            $stemmedQueryTerms[] = $stemmed;
        }
        $stemmedQueryTerms = array_values(array_unique($stemmedQueryTerms));

        // 2. Candidate collection & Lexical Scoring
        $candidateScores = []; // docId => lexical_score
        $boostFields = $query->getBoostFields();

        if (!empty($stemmedQueryTerms)) {
            foreach ($stemmedQueryTerms as $term) {
                if (!isset($this->postings[$term])) {
                    // Check for fuzzy prefix matching if fuzzy search is active
                    if ($query->isFuzzy()) {
                        foreach ($this->postings as $idxTerm => $docPostings) {
                            if (str_starts_with($idxTerm, $term) || levenshtein($term, $idxTerm) <= 1) {
                                $this->accumulateTermScores($idxTerm, $docPostings, $candidateScores, $totalDocs, $avgDocLength, $boostFields);
                            }
                        }
                    }
                    continue;
                }

                $docPostings = $this->postings[$term];
                $this->accumulateTermScores($term, $docPostings, $candidateScores, $totalDocs, $avgDocLength, $boostFields);
            }
        } elseif ($vector !== null || !empty($query->getFilters())) {
            // Match all documents as base set for vector-only or filter-only queries
            foreach ($this->documents as $docId => $doc) {
                $candidateScores[$docId] = 0.0;
            }
        }

        // 3. Filter Evaluation (exact matches & ranges)
        $filteredCandidates = [];
        foreach ($candidateScores as $docId => $lexicalScore) {
            $doc = $this->documents[$docId] ?? null;
            if ($doc === null) {
                continue;
            }

            if (!$this->matchesFilters($doc, $query->getFilters())) {
                continue;
            }

            $filteredCandidates[$docId] = [
                'document' => $doc,
                'score' => $lexicalScore * $doc->getBoost(),
                'lexical_score' => $lexicalScore,
            ];
        }

        // 4. Hybrid Neural Reranking (BM25 + Dense Vector)
        $rerankedHits = $this->reranker->rerank(array_values($filteredCandidates), $query);

        // 5. Sorting
        $sortField = $query->getSortField();
        if ($sortField !== null) {
            $sortOrder = $query->getSortOrder();
            usort($rerankedHits, function ($a, $b) use ($sortField, $sortOrder) {
                $valA = $a['document']->get($sortField);
                $valB = $b['document']->get($sortField);
                $cmp = ($valA <=> $valB);
                return $sortOrder === 'asc' ? $cmp : -$cmp;
            });
        }

        $totalMatches = count($rerankedHits);

        // 6. Pagination (Offset & Limit)
        $pagedHits = array_slice($rerankedHits, $query->getOffset(), $query->getLimit());

        // 7. Match Highlighting
        if ($query->shouldHighlight() && !empty($rawTokens)) {
            foreach ($pagedHits as &$hit) {
                $hit['highlight'] = $this->generateHighlight($hit['document'], $rawTokens);
            }
            unset($hit);
        }

        // 8. Faceted Taxonomy Aggregation
        $facetResults = [];
        if (!empty($query->getFacets())) {
            $matchingDocs = array_map(fn($h) => $h['document'], $rerankedHits);
            $facetResults = $this->facetEngine->computeFacets($matchingDocs, $query->getFacets());
        }

        $maxScore = !empty($pagedHits) ? (float)max(array_column($pagedHits, 'score')) : 0.0;
        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 3);

        return new SearchResult(
            hits: $pagedHits,
            total: $totalMatches,
            executionTimeMs: $executionTimeMs,
            facets: $facetResults,
            suggestions: [],
            maxScore: $maxScore
        );
    }

    protected function accumulateTermScores(
        string $term,
        array $docPostings,
        array &$candidateScores,
        int $totalDocs,
        float $avgDocLength,
        array $boostFields
    ): void {
        $df = count($docPostings);

        foreach ($docPostings as $docId => $fieldPostings) {
            $docLen = $this->docLengths[$docId] ?? (int)$avgDocLength;
            $termScore = 0.0;

            foreach ($fieldPostings as $field => $posting) {
                $tf = $posting->frequency;
                $fieldWeight = $boostFields[$field] ?? 1.0;
                $score = $this->bm25Scorer->score($tf, $df, $totalDocs, $docLen, $avgDocLength);
                $termScore += ($score * $fieldWeight);
            }

            $candidateScores[$docId] = ($candidateScores[$docId] ?? 0.0) + $termScore;
        }
    }

    protected function matchesFilters(Document $doc, array $filters): bool
    {
        foreach ($filters as $field => $filterValue) {
            $docVal = $doc->get($field);

            if (is_array($filterValue)) {
                // Range filter ['min' => 10, 'max' => 50] or IN filter ['in' => ['a', 'b']]
                if (isset($filterValue['min']) && ((float)$docVal < (float)$filterValue['min'])) {
                    return false;
                }
                if (isset($filterValue['max']) && ((float)$docVal > (float)$filterValue['max'])) {
                    return false;
                }
                if (isset($filterValue['in']) && is_array($filterValue['in']) && !in_array($docVal, $filterValue['in'], false)) {
                    return false;
                }
            } else {
                if ($docVal !== $filterValue && (string)$docVal !== (string)$filterValue) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function generateHighlight(Document $doc, array $terms): string
    {
        $text = $doc->getCombinedText(['id', 'vector']);
        if ($text === '') {
            return '';
        }

        $escapedTerms = array_map(fn($t) => preg_quote($t, '/'), $terms);
        $pattern = '/\b(' . implode('|', $escapedTerms) . ')\b/iu';

        // Extract first occurrence snippet
        if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
            $offset = (int)$match[0][1];
            $start = max(0, $offset - 60);
            $length = min(strlen($text) - $start, 160);
            $snippet = substr($text, $start, $length);
            if ($start > 0) {
                $snippet = '...' . $snippet;
            }
            if ($start + $length < strlen($text)) {
                $snippet .= '...';
            }
            return (string)preg_replace($pattern, '<mark>$1</mark>', $snippet);
        }

        return mb_substr($text, 0, 100, 'UTF-8') . '...';
    }
}
