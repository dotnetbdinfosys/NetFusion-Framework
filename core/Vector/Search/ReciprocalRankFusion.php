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

class ReciprocalRankFusion
{
    /**
     * Blend and rank dense and sparse search results using Reciprocal Rank Fusion (RRF).
     *
     * @param array<array{id: string, score: float, metadata: array<string, mixed>}> $denseResults
     * @param array<array{id: string, score: float, metadata: array<string, mixed>}> $sparseResults
     * @param int $k RRF smoothing constant (default 60)
     * @param float $denseWeight Relative weight of dense results (default 1.0)
     * @param float $sparseWeight Relative weight of sparse results (default 1.0)
     * @param int $topK Total top results to return
     * @return array<array{id: string, score: float, dense_score: ?float, sparse_score: ?float, dense_rank: ?int, sparse_rank: ?int, metadata: array<string, mixed>}>
     */
    public static function fuse(
        array $denseResults,
        array $sparseResults,
        int $k = 60,
        float $denseWeight = 1.0,
        float $sparseWeight = 1.0,
        int $topK = 10
    ): array {
        $fusedScores = [];
        $metadataMap = [];
        $denseScoreMap = [];
        $sparseScoreMap = [];
        $denseRankMap = [];
        $sparseRankMap = [];

        // 1. Process Dense Ranked Results
        foreach ($denseResults as $rank => $item) {
            $id = $item['id'];
            $rank1Based = $rank + 1;
            $rrfScore = $denseWeight / ($k + $rank1Based);

            $fusedScores[$id] = ($fusedScores[$id] ?? 0.0) + $rrfScore;
            $metadataMap[$id] = $item['metadata'] ?? [];
            $denseScoreMap[$id] = $item['score'] ?? null;
            $denseRankMap[$id] = $rank1Based;
        }

        // 2. Process Sparse (BM25) Ranked Results
        foreach ($sparseResults as $rank => $item) {
            $id = $item['id'];
            $rank1Based = $rank + 1;
            $rrfScore = $sparseWeight / ($k + $rank1Based);

            $fusedScores[$id] = ($fusedScores[$id] ?? 0.0) + $rrfScore;
            if (!isset($metadataMap[$id])) {
                $metadataMap[$id] = $item['metadata'] ?? [];
            }
            $sparseScoreMap[$id] = $item['score'] ?? null;
            $sparseRankMap[$id] = $rank1Based;
        }

        // 3. Construct Final Combined Results
        $results = [];
        foreach ($fusedScores as $id => $score) {
            $results[] = [
                'id' => $id,
                'score' => (float)$score,
                'dense_score' => $denseScoreMap[$id] ?? null,
                'sparse_score' => $sparseScoreMap[$id] ?? null,
                'dense_rank' => $denseRankMap[$id] ?? null,
                'sparse_rank' => $sparseRankMap[$id] ?? null,
                'metadata' => $metadataMap[$id] ?? [],
            ];
        }

        // Sort descending by combined RRF score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $topK);
    }

    /**
     * Score blending with min-max normalization.
     * Score = alpha * norm(dense) + (1 - alpha) * norm(sparse)
     *
     * @param array<array{id: string, score: float, metadata: array<string, mixed>}> $denseResults
     * @param array<array{id: string, score: float, metadata: array<string, mixed>}> $sparseResults
     * @param float $alpha Weight for dense score (0.0 to 1.0). 1.0 = dense only, 0.0 = sparse only.
     * @param int $topK
     * @return array<array{id: string, score: float, dense_score: ?float, sparse_score: ?float, metadata: array<string, mixed>}>
     */
    public static function blend(
        array $denseResults,
        array $sparseResults,
        float $alpha = 0.5,
        int $topK = 10
    ): array {
        $normDense = self::normalizeScores($denseResults);
        $normSparse = self::normalizeScores($sparseResults);

        $allIds = array_unique(array_merge(array_keys($normDense), array_keys($normSparse)));
        $results = [];

        foreach ($allIds as $id) {
            $dScore = $normDense[$id]['norm_score'] ?? 0.0;
            $sScore = $normSparse[$id]['norm_score'] ?? 0.0;

            $blended = ($alpha * $dScore) + ((1.0 - $alpha) * $sScore);
            $metadata = $normDense[$id]['metadata'] ?? $normSparse[$id]['metadata'] ?? [];

            $results[] = [
                'id' => $id,
                'score' => (float)$blended,
                'dense_score' => $normDense[$id]['raw_score'] ?? null,
                'sparse_score' => $normSparse[$id]['raw_score'] ?? null,
                'metadata' => $metadata,
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $topK);
    }

    /**
     * Min-max normalize scores to [0.0, 1.0].
     *
     * @param array<array{id: string, score: float, metadata: array<string, mixed>}> $results
     * @return array<string, array{raw_score: float, norm_score: float, metadata: array<string, mixed>}>
     */
    protected static function normalizeScores(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        $scores = array_column($results, 'score');
        $min = min($scores);
        $max = max($scores);
        $range = $max - $min;

        $mapped = [];
        foreach ($results as $item) {
            $raw = (float)$item['score'];
            $norm = ($range > 1e-12) ? (($raw - $min) / $range) : 1.0;
            $mapped[$item['id']] = [
                'raw_score' => $raw,
                'norm_score' => $norm,
                'metadata' => $item['metadata'] ?? [],
            ];
        }

        return $mapped;
    }
}
