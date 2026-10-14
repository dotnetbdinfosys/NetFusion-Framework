<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Search/Drivers/MemoryDriver.php
| Module    : Search
| Namespace : NetFusion\Core\Search\Drivers
| Purpose   : High-performance in-memory inverted index driver with BM25 ranking.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Search\Drivers;

use NetFusion\Core\Search\Contracts\SearchDriverInterface;

/**
 * Class MemoryDriver
 *
 * In-memory full-text search engine with Inverted Indexing, tokenization,
 * Okapi BM25 relevance scoring, field boosting, and attribute filtering.
 *
 * @package NetFusion\Core\Search\Drivers
 */
class MemoryDriver implements SearchDriverInterface
{
    /**
     * Storage of documents by index.
     * Format: [$index => [$docId => $docPayload]]
     *
     * @var array<string, array<string|int, array<string, mixed>>>
     */
    protected array $documents = [];

    /**
     * Inverted index mapping terms to document IDs and frequencies.
     * Format: [$index => [$term => [$docId => $termFrequency]]]
     *
     * @var array<string, array<string, array<string|int, int>>>
     */
    protected array $invertedIndex = [];

    /**
     * Document lengths (total tokens per document).
     * Format: [$index => [$docId => $tokenCount]]
     *
     * @var array<string, array<string|int, int>>
     */
    protected array $docLengths = [];

    /**
     * Field weight boosters (e.g. title matches are prioritized over body).
     *
     * @var array<string, float>
     */
    protected array $fieldBoosts = [
        'title' => 2.5,
        'name'  => 2.5,
        'tag'   => 1.5,
        'tags'  => 1.5,
    ];

    /**
     * Common English stopwords to optionally ignore or filter.
     *
     * @var array<string, bool>
     */
    protected array $stopwords = [
        'a' => true, 'an' => true, 'and' => true, 'are' => true, 'as' => true,
        'at' => true, 'be' => true, 'by' => true, 'for' => true, 'from' => true,
        'has' => true, 'he' => true, 'in' => true, 'is' => true, 'it' => true,
        'its' => true, 'of' => true, 'on' => true, 'that' => true, 'the' => true,
        'to' => true, 'was' => true, 'were' => true, 'will' => true, 'with' => true,
    ];

    /**
     * BM25 parameter k1 (term frequency saturation).
     */
    protected float $k1 = 1.2;

    /**
     * BM25 parameter b (document length penalization).
     */
    protected float $b = 0.75;

    /**
     * {@inheritdoc}
     */
    public function search(string $index, string $query, array $options = []): array
    {
        $startTime = microtime(true);

        if (! isset($this->documents[$index]) || empty($this->documents[$index])) {
            return [
                'hits'  => [],
                'total' => 0,
                'took'  => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        $queryTokens = $this->tokenize($query, false);
        if (empty($queryTokens)) {
            return [
                'hits'  => [],
                'total' => 0,
                'took'  => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        $totalDocs = count($this->documents[$index]);
        $totalDocLength = array_sum($this->docLengths[$index] ?? [0]);
        $avgDocLength = $totalDocs > 0 ? ($totalDocLength / $totalDocs) : 1.0;
        if ($avgDocLength <= 0) {
            $avgDocLength = 1.0;
        }

        $scores = [];
        $filters = $options['filters'] ?? [];

        foreach ($queryTokens as $qToken) {
            $isWildcard = str_ends_with($qToken, '*');
            $cleanToken = rtrim($qToken, '*');

            // Find matching index terms
            $matchingTerms = [];
            if ($isWildcard || (strlen($cleanToken) >= 3 && ($options['fuzzy'] ?? false))) {
                foreach (array_keys($this->invertedIndex[$index] ?? []) as $term) {
                    $termStr = (string) $term;
                    if (str_starts_with($termStr, $cleanToken)) {
                        $matchingTerms[] = $termStr;
                    }
                }
            } elseif (isset($this->invertedIndex[$index][$cleanToken])) {
                $matchingTerms[] = $cleanToken;
            }

            foreach ($matchingTerms as $term) {
                $postings = $this->invertedIndex[$index][$term] ?? [];
                $docCountWithTerm = count($postings);
                if ($docCountWithTerm === 0) {
                    continue;
                }

                // Okapi BM25 Inverse Document Frequency (IDF)
                $idf = log(1.0 + (($totalDocs - $docCountWithTerm + 0.5) / ($docCountWithTerm + 0.5)));
                if ($idf <= 0) {
                    $idf = 0.05; // Base floor to prevent zeroing common terms
                }

                foreach ($postings as $docId => $tf) {
                    // Check attribute filters if provided
                    if (! empty($filters) && ! $this->matchesFilters($this->documents[$index][$docId], $filters)) {
                        continue;
                    }

                    $docLen = $this->docLengths[$index][$docId] ?? 1;
                    $denom = $tf + $this->k1 * (1.0 - $this->b + $this->b * ($docLen / $avgDocLength));
                    $bm25 = $idf * (($tf * ($this->k1 + 1.0)) / ($denom > 0 ? $denom : 1.0));

                    $scores[$docId] = ($scores[$docId] ?? 0.0) + $bm25;
                }
            }
        }

        // Apply field boost bonus
        foreach ($scores as $docId => $score) {
            $doc = $this->documents[$index][$docId];
            $bonus = 1.0;
            foreach ($this->fieldBoosts as $field => $boost) {
                if (isset($doc[$field]) && is_string($doc[$field])) {
                    $fieldTokens = $this->tokenize($doc[$field]);
                    foreach ($queryTokens as $qToken) {
                        $clean = rtrim($qToken, '*');
                        if (in_array($clean, $fieldTokens, true)) {
                            $bonus += ($boost - 1.0);
                        }
                    }
                }
            }
            $scores[$docId] = $score * $bonus;
        }

        // Sort descending by score
        arsort($scores, SORT_NUMERIC);

        $total = count($scores);
        $limit = isset($options['limit']) ? (int) $options['limit'] : 15;
        $offset = isset($options['offset']) ? (int) $options['offset'] : 0;

        $slicedScores = array_slice($scores, $offset, $limit, true);

        $hits = [];
        foreach ($slicedScores as $docId => $score) {
            $hits[] = [
                'id'       => $docId,
                'score'    => round((float) $score, 4),
                'document' => $this->documents[$index][$docId],
            ];
        }

        $took = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'hits'  => $hits,
            'total' => $total,
            'took'  => $took,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function index(string $index, array $documents): void
    {
        $this->ensureIndexExists($index);

        foreach ($documents as $doc) {
            $docId = $doc['id'] ?? $doc['ID'] ?? null;
            if ($docId === null) {
                continue;
            }

            // Remove existing index entry if updating
            if (isset($this->documents[$index][$docId])) {
                $this->delete($index, $docId);
            }

            $this->documents[$index][$docId] = $doc;

            $tokenStream = [];
            foreach ($doc as $key => $val) {
                if (is_string($val) || is_numeric($val)) {
                    $tokens = $this->tokenize((string) $val);
                    foreach ($tokens as $token) {
                        $tokenStream[] = $token;
                    }
                } elseif (is_array($val)) {
                    foreach ($val as $subVal) {
                        if (is_string($subVal) || is_numeric($subVal)) {
                            $tokens = $this->tokenize((string) $subVal);
                            foreach ($tokens as $token) {
                                $tokenStream[] = $token;
                            }
                        }
                    }
                }
            }

            $this->docLengths[$index][$docId] = count($tokenStream);

            // Compute term frequencies
            $termFreqs = array_count_values($tokenStream);
            foreach ($termFreqs as $term => $tf) {
                $this->invertedIndex[$index][$term][$docId] = $tf;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $index, array|int|string $ids): void
    {
        if (! isset($this->documents[$index])) {
            return;
        }

        $idList = is_array($ids) ? $ids : [$ids];

        foreach ($idList as $id) {
            unset($this->documents[$index][$id]);
            unset($this->docLengths[$index][$id]);

            foreach ($this->invertedIndex[$index] as $term => &$postings) {
                unset($postings[$id]);
            }
            unset($postings);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function flush(string $index): void
    {
        $this->documents[$index] = [];
        $this->invertedIndex[$index] = [];
        $this->docLengths[$index] = [];
    }

    /**
     * {@inheritdoc}
     */
    public function createIndex(string $index, array $fields = []): void
    {
        $this->ensureIndexExists($index);
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(string $index): void
    {
        unset($this->documents[$index]);
        unset($this->invertedIndex[$index]);
        unset($this->docLengths[$index]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(string $index): bool
    {
        return isset($this->documents[$index]);
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $index): int
    {
        return isset($this->documents[$index]) ? count($this->documents[$index]) : 0;
    }

    /**
     * Tokenize text into normalized lowercase tokens.
     *
     * @param string $text
     * @param bool   $removeStopwords
     * @return array<string>
     */
    public function tokenize(string $text, bool $removeStopwords = true): array
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        // Replace non-alphanumeric (allowing * for wildcard matching) with space
        $clean = preg_replace('/[^\p{L}\p{N}\*]+/u', ' ', $normalized);
        if ($clean === null || $clean === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($words as $word) {
            $trimmed = trim($word);
            if ($trimmed === '' || $trimmed === '*') {
                continue;
            }

            if ($removeStopwords && isset($this->stopwords[$trimmed]) && ! str_ends_with($trimmed, '*')) {
                continue;
            }

            $tokens[] = $trimmed;
        }

        return $tokens;
    }

    /**
     * Check if a document matches the given key-value filters.
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed> $filters
     * @return bool
     */
    protected function matchesFilters(array $document, array $filters): bool
    {
        foreach ($filters as $key => $expected) {
            if (! array_key_exists($key, $document)) {
                return false;
            }

            $val = $document[$key];
            if (is_array($expected)) {
                if (! in_array($val, $expected, false)) {
                    return false;
                }
            } elseif ((string) $val !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure storage arrays exist for the index.
     *
     * @param string $index
     * @return void
     */
    protected function ensureIndexExists(string $index): void
    {
        if (! isset($this->documents[$index])) {
            $this->documents[$index] = [];
            $this->invertedIndex[$index] = [];
            $this->docLengths[$index] = [];
        }
    }
}
