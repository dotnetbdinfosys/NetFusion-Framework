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

namespace NetFusion\Core\Vector\Search;

use JsonSerializable;

class BM25Index implements JsonSerializable
{
    /**
     * Inverted index: [term => [docId => term_frequency]]
     * @var array<string, array<string, int>>
     */
    protected array $invertedIndex = [];

    /**
     * Document lengths: [docId => length_in_tokens]
     * @var array<string, int>
     */
    protected array $docLengths = [];

    /**
     * Document raw metadata: [docId => metadata]
     * @var array<string, array<string, mixed>>
     */
    protected array $docMetadata = [];

    /**
     * Total number of documents in index.
     */
    protected int $totalDocs = 0;

    /**
     * Average document length across the collection.
     */
    protected float $avgDocLength = 0.0;

    /**
     * @param float $k1 Term frequency saturation parameter (default 1.2)
     * @param float $b Document length normalization parameter (default 0.75)
     */
    public function __construct(
        protected float $k1 = 1.2,
        protected float $b = 0.75
    ) {
    }

    /**
     * Tokenize and normalize text into words/terms.
     *
     * @return array<string>
     */
    public function tokenize(string $text): array
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        // Replace non-alphanumeric characters with spaces
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized);
        $tokens = preg_split('/\s+/u', (string)$clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $tokens;
    }

    /**
     * Add or update a document in the BM25 index.
     *
     * @param string $docId Unique document ID
     * @param string $text Document textual content
     * @param array<string, mixed> $metadata
     */
    public function insert(string $docId, string $text, array $metadata = []): void
    {
        if (isset($this->docLengths[$docId])) {
            $this->delete($docId);
        }

        $tokens = $this->tokenize($text);
        $len = count($tokens);

        $this->docLengths[$docId] = $len;
        $this->docMetadata[$docId] = $metadata;
        $this->totalDocs++;

        // Compute term frequencies
        $termCounts = array_count_values($tokens);
        foreach ($termCounts as $term => $count) {
            if (!isset($this->invertedIndex[$term])) {
                $this->invertedIndex[$term] = [];
            }
            $this->invertedIndex[$term][$docId] = $count;
        }

        $this->recalculateAvgDocLength();
    }

    /**
     * Search documents using BM25 scoring algorithm.
     *
     * @param string $query Natural language search query
     * @param int $k Number of top documents to retrieve
     * @param array<string, mixed> $filter
     * @return array<array{id: string, score: float, metadata: array<string, mixed>}>
     */
    public function search(string $query, int $k = 10, array $filter = []): array
    {
        $queryTokens = $this->tokenize($query);
        if (empty($queryTokens) || $this->totalDocs === 0) {
            return [];
        }

        $scores = [];
        $queryTermCounts = array_count_values($queryTokens);

        foreach ($queryTermCounts as $term => $qtf) {
            if (!isset($this->invertedIndex[$term])) {
                continue;
            }

            $docList = $this->invertedIndex[$term];
            $df = count($docList); // Document frequency for term

            // Okapi BM25 Robertson-Spärck Jones IDF
            $idf = log(($this->totalDocs - $df + 0.5) / ($df + 0.5) + 1.0);
            if ($idf <= 0.0) {
                $idf = 0.01; // Floor for very frequent terms
            }

            foreach ($docList as $docId => $tf) {
                $docLen = $this->docLengths[$docId] ?? 1;
                $lenNorm = 1.0 - $this->b + ($this->b * ($docLen / max(1.0, $this->avgDocLength)));
                $tfWeight = ($tf * ($this->k1 + 1.0)) / ($tf + ($this->k1 * $lenNorm));

                $termScore = $idf * $tfWeight;
                $scores[$docId] = ($scores[$docId] ?? 0.0) + $termScore;
            }
        }

        // Apply metadata filters
        $results = [];
        foreach ($scores as $docId => $score) {
            $metadata = $this->docMetadata[$docId] ?? [];
            if (!empty($filter) && !$this->matchesFilter($metadata, $filter)) {
                continue;
            }

            $results[] = [
                'id' => $docId,
                'score' => (float)$score,
                'metadata' => $metadata,
            ];
        }

        // Sort descending by BM25 score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $k);
    }

    protected function matchesFilter(array $metadata, array $filter): bool
    {
        foreach ($filter as $key => $expected) {
            $actual = $metadata[$key] ?? null;
            if (is_array($expected)) {
                if (!in_array($actual, $expected, false)) {
                    return false;
                }
            } else {
                if ($actual != $expected) {
                    return false;
                }
            }
        }

        return true;
    }

    public function delete(string $docId): bool
    {
        if (!isset($this->docLengths[$docId])) {
            return false;
        }

        unset($this->docLengths[$docId]);
        unset($this->docMetadata[$docId]);
        $this->totalDocs--;

        foreach ($this->invertedIndex as $term => &$docList) {
            unset($docList[$docId]);
        }
        unset($docList);

        // Remove empty terms
        $this->invertedIndex = array_filter($this->invertedIndex, fn($list) => !empty($list));

        $this->recalculateAvgDocLength();
        return true;
    }

    protected function recalculateAvgDocLength(): void
    {
        if ($this->totalDocs <= 0) {
            $this->avgDocLength = 0.0;
            return;
        }

        $totalTokens = array_sum($this->docLengths);
        $this->avgDocLength = (float)($totalTokens / $this->totalDocs);
    }

    public function count(): int
    {
        return $this->totalDocs;
    }

    public function clear(): void
    {
        $this->invertedIndex = [];
        $this->docLengths = [];
        $this->docMetadata = [];
        $this->totalDocs = 0;
        $this->avgDocLength = 0.0;
    }

    public function jsonSerialize(): array
    {
        return [
            'k1' => $this->k1,
            'b' => $this->b,
            'totalDocs' => $this->totalDocs,
            'avgDocLength' => $this->avgDocLength,
            'docLengths' => $this->docLengths,
            'docMetadata' => $this->docMetadata,
            'invertedIndex' => $this->invertedIndex,
        ];
    }

    public static function fromArray(array $data): self
    {
        $index = new self(
            (float)($data['k1'] ?? 1.2),
            (float)($data['b'] ?? 0.75)
        );

        $index->totalDocs = (int)($data['totalDocs'] ?? 0);
        $index->avgDocLength = (float)($data['avgDocLength'] ?? 0.0);
        $index->docLengths = (array)($data['docLengths'] ?? []);
        $index->docMetadata = (array)($data['docMetadata'] ?? []);
        $index->invertedIndex = (array)($data['invertedIndex'] ?? []);

        return $index;
    }
}
