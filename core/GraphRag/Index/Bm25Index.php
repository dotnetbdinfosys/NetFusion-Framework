<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\GraphRag\Index;

/**
 * Pure PHP Okapi BM25 sparse inverted index for lexical relevance scoring.
 */
class Bm25Index
{
    /** @var array<string, array<string, int>> docId => [term => frequency] */
    protected array $docTermFreqs = [];

    /** @var array<string, int> docId => document length in tokens */
    protected array $docLengths = [];

    /** @var array<string, int> term => number of documents containing term */
    protected array $docFreqs = [];

    protected int $totalDocCount = 0;
    protected float $avgDocLength = 0.0;

    /**
     * @param float $k1 BM25 term frequency saturation parameter (typically 1.2 to 2.0)
     * @param float $b  BM25 document length normalization parameter (typically 0.75)
     */
    public function __construct(
        protected float $k1 = 1.5,
        protected float $b = 0.75
    ) {
    }

    /**
     * Index a document by its ID and text body.
     */
    public function addDocument(string $docId, string $text): void
    {
        $this->removeDocument($docId);

        $tokens = $this->tokenize($text);
        $length = count($tokens);
        if ($length === 0) {
            return;
        }

        $termFreqs = array_count_values($tokens);
        $this->docTermFreqs[$docId] = $termFreqs;
        $this->docLengths[$docId] = $length;

        foreach (array_keys($termFreqs) as $term) {
            $this->docFreqs[$term] = ($this->docFreqs[$term] ?? 0) + 1;
        }

        $this->totalDocCount++;
        $this->recomputeAvgDocLength();
    }

    /**
     * Remove a document from the inverted index.
     */
    public function removeDocument(string $docId): void
    {
        if (!isset($this->docTermFreqs[$docId])) {
            return;
        }

        $termFreqs = $this->docTermFreqs[$docId];
        foreach (array_keys($termFreqs) as $term) {
            if (isset($this->docFreqs[$term])) {
                $this->docFreqs[$term]--;
                if ($this->docFreqs[$term] <= 0) {
                    unset($this->docFreqs[$term]);
                }
            }
        }

        unset($this->docTermFreqs[$docId]);
        unset($this->docLengths[$docId]);

        $this->totalDocCount--;
        $this->recomputeAvgDocLength();
    }

    /**
     * Execute Okapi BM25 search query and return sorted scores.
     *
     * @param string $query
     * @param int $topK
     * @return array<string, float> docId => bm25Score
     */
    public function search(string $query, int $topK = 10): array
    {
        $queryTokens = $this->tokenize($query);
        if (empty($queryTokens) || $this->totalDocCount === 0 || $this->avgDocLength <= 0.0) {
            return [];
        }

        $scores = [];

        foreach ($queryTokens as $term) {
            $df = $this->docFreqs[$term] ?? 0;
            if ($df === 0) {
                continue;
            }

            // Robertson-Spärck Jones IDF with smoothing
            $idf = log(($this->totalDocCount - $df + 0.5) / ($df + 0.5) + 1.0);

            foreach ($this->docTermFreqs as $docId => $tfs) {
                $tf = $tfs[$term] ?? 0;
                if ($tf === 0) {
                    continue;
                }

                $docLen = $this->docLengths[$docId];
                $lenNorm = 1.0 - $this->b + ($this->b * ($docLen / $this->avgDocLength));
                $tfPart = ($tf * ($this->k1 + 1.0)) / ($tf + ($this->k1 * $lenNorm));

                $termScore = $idf * $tfPart;
                $scores[$docId] = ($scores[$docId] ?? 0.0) + $termScore;
            }
        }

        arsort($scores, SORT_NUMERIC);
        return array_slice($scores, 0, $topK, true);
    }

    public function count(): int
    {
        return $this->totalDocCount;
    }

    protected function recomputeAvgDocLength(): void
    {
        if ($this->totalDocCount === 0) {
            $this->avgDocLength = 0.0;
            return;
        }

        $this->avgDocLength = array_sum($this->docLengths) / $this->totalDocCount;
    }

    /**
     * Tokenize text into normalized lowercase alphanumeric tokens.
     *
     * @return array<int, string>
     */
    public function tokenize(string $text): array
    {
        $clean = mb_strtolower($text, 'UTF-8');
        preg_match_all('/[\p{L}\p{N}]+/u', $clean, $matches);
        return $matches[0] ?? [];
    }
}
