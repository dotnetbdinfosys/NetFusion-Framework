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

namespace NetFusion\Core\Search\Scoring;

use NetFusion\Core\Search\Contracts\RerankerInterface;
use NetFusion\Core\Search\Models\Document;
use NetFusion\Core\Search\Models\SearchQuery;

class HybridReranker implements RerankerInterface
{
    /**
     * @param int $rrfConstant Constant k for Reciprocal Rank Fusion (standard 60)
     */
    public function __construct(
        protected int $rrfConstant = 60
    ) {
    }

    /**
     * Calculate cosine similarity between two numeric float vectors.
     *
     * @param array<float> $a
     * @param array<float> $b
     * @return float Score in [-1.0, 1.0]
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $count = count($a);
        if ($count === 0 || $count !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $valA = (float)$a[$i];
            $valB = (float)$b[$i];
            $dotProduct += ($valA * $valB);
            $normA += ($valA * $valA);
            $normB += ($valB * $valB);
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Rerank retrieved candidate documents using hybrid blend of BM25 lexical score, dense vector cosine similarity, and document boosts.
     *
     * @param array<array{document: Document, score: float, lexical_score?: float, vector_score?: float}> $candidates
     * @param SearchQuery $query
     * @return array<array{document: Document, score: float, lexical_score?: float, vector_score?: float}>
     */
    public function rerank(array $candidates, SearchQuery $query): array
    {
        if (empty($candidates)) {
            return [];
        }

        $queryVector = $query->getVector();
        $hybridWeight = $query->getHybridWeight(); // 1.0 = pure BM25, 0.0 = pure vector, 0.5 = hybrid

        // Compute vector scores if query has a vector
        foreach ($candidates as &$c) {
            $docVector = $c['document']->getVector();
            if ($queryVector !== null && $docVector !== null) {
                $cosSim = self::cosineSimilarity($queryVector, $docVector);
                // Map cosine similarity [-1, 1] to [0, 1]
                $c['vector_score'] = max(0.0, ($cosSim + 1.0) / 2.0);
            } else {
                $c['vector_score'] = 0.0;
            }
        }
        unset($c);

        // If no vector search is needed, just sort by lexical score * doc boost
        if ($queryVector === null || $hybridWeight >= 1.0) {
            usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
            return $candidates;
        }

        // Apply Reciprocal Rank Fusion (RRF) & Weighted Normalized Blending
        $maxLexical = 0.0001;
        $maxVector = 0.0001;
        foreach ($candidates as $c) {
            if (($c['lexical_score'] ?? 0.0) > $maxLexical) {
                $maxLexical = $c['lexical_score'];
            }
            if (($c['vector_score'] ?? 0.0) > $maxVector) {
                $maxVector = $c['vector_score'];
            }
        }

        foreach ($candidates as &$c) {
            $normLexical = ($c['lexical_score'] ?? 0.0) / $maxLexical;
            $normVector = ($c['vector_score'] ?? 0.0) / $maxVector;

            // Blended composite score
            $blended = ($hybridWeight * $normLexical) + ((1.0 - $hybridWeight) * $normVector);

            // Multiply by static document boost
            $boost = $c['document']->getBoost();
            $c['score'] = round($blended * $boost, 6);
        }
        unset($c);

        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        return $candidates;
    }

    /**
     * Compute standalone Reciprocal Rank Fusion (RRF) on multiple ranked lists.
     *
     * @param array<string, array<string>> $rankedLists SystemName => [DocId1, DocId2, ...]
     * @return array<string, float> DocId => RRF_Score
     */
    public function computeRRF(array $rankedLists): array
    {
        $scores = [];
        $k = $this->rrfConstant;

        foreach ($rankedLists as $system => $docList) {
            foreach ($docList as $rank => $docId) {
                // Rank is 1-based index
                $r = $rank + 1;
                $rrfScore = 1.0 / ($k + $r);
                $scores[$docId] = ($scores[$docId] ?? 0.0) + $rrfScore;
            }
        }

        arsort($scores);
        return $scores;
    }
}
