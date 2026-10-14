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

use NetFusion\Core\GraphRag\Exceptions\EmbeddingMismatchException;

/**
 * Mathematical vector similarity metrics for dense embedding spaces.
 */
class VectorSimilarity
{
    /**
     * Compute Cosine Similarity between two float vectors.
     * Returns a score in the range [-1.0, 1.0].
     *
     * @param array<int, float> $vecA
     * @param array<int, float> $vecB
     */
    public static function cosine(array $vecA, array $vecB): float
    {
        $countA = count($vecA);
        $countB = count($vecB);

        if ($countA === 0 || $countB === 0) {
            return 0.0;
        }

        if ($countA !== $countB) {
            throw new EmbeddingMismatchException("Embedding dimension mismatch: vector A has {$countA} dimensions, vector B has {$countB}.");
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $countA; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];
            $dot += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Compute Dot Product between two vectors.
     *
     * @param array<int, float> $vecA
     * @param array<int, float> $vecB
     */
    public static function dotProduct(array $vecA, array $vecB): float
    {
        $count = min(count($vecA), count($vecB));
        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $sum += ((float) $vecA[$i]) * ((float) $vecB[$i]);
        }

        return $sum;
    }

    /**
     * Compute Euclidean Distance between two vectors.
     *
     * @param array<int, float> $vecA
     * @param array<int, float> $vecB
     */
    public static function euclidean(array $vecA, array $vecB): float
    {
        $countA = count($vecA);
        $countB = count($vecB);

        if ($countA !== $countB) {
            throw new EmbeddingMismatchException("Embedding dimension mismatch: {$countA} vs {$countB}.");
        }

        $sum = 0.0;
        for ($i = 0; $i < $countA; $i++) {
            $diff = ((float) $vecA[$i]) - ((float) $vecB[$i]);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
