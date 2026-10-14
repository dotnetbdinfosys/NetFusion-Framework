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

namespace NetFusion\Core\AI\Vector;

use InvalidArgumentException;

class VectorMath
{
    /**
     * Compute the dot product of two vectors.
     *
     * @param array<float> $a
     * @param array<float> $b
     * @return float
     */
    public static function dotProduct(array $a, array $b): float
    {
        $count = min(count($a), count($b));
        if ($count === 0) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $sum += ((float)$a[$i] * (float)$b[$i]);
        }

        return $sum;
    }

    /**
     * Calculate Euclidean magnitude (L2 norm) of a vector.
     *
     * @param array<float> $a
     * @return float
     */
    public static function magnitude(array $a): float
    {
        $sumSq = 0.0;
        foreach ($a as $val) {
            $sumSq += ((float)$val * (float)$val);
        }

        return sqrt($sumSq);
    }

    /**
     * Calculate Cosine Similarity between two vectors [-1.0 to 1.0].
     *
     * @param array<float> $a
     * @param array<float> $b
     * @return float
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $magA = self::magnitude($a);
        $magB = self::magnitude($b);

        if ($magA <= 0.0 || $magB <= 0.0) {
            return 0.0;
        }

        $dot = self::dotProduct($a, $b);
        $sim = $dot / ($magA * $magB);

        return max(-1.0, min(1.0, $sim));
    }

    /**
     * Calculate Euclidean distance between two vectors.
     *
     * @param array<float> $a
     * @param array<float> $b
     * @return float
     */
    public static function euclideanDistance(array $a, array $b): float
    {
        $count = max(count($a), count($b));
        if ($count === 0) {
            return 0.0;
        }

        $sumSq = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $diff = ((float)($a[$i] ?? 0.0)) - ((float)($b[$i] ?? 0.0));
            $sumSq += ($diff * $diff);
        }

        return sqrt($sumSq);
    }

    /**
     * Normalize a vector to unit length (L2 norm = 1.0).
     *
     * @param array<float> $a
     * @return array<float>
     */
    public static function normalize(array $a): array
    {
        $mag = self::magnitude($a);
        if ($mag <= 0.0) {
            return array_fill(0, count($a), 0.0);
        }

        return array_map(fn($v) => (float)$v / $mag, $a);
    }

    /**
     * Generate simple TF-IDF / Bag of Words vector for fallback embedding.
     *
     * @param string $text
     * @param array<string> $vocabulary
     * @return array<float>
     */
    public static function simpleTfIdf(string $text, array $vocabulary): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $counts = array_count_values($words ?: []);
        $totalWords = max(1, count($words ?: []));

        $vector = [];
        foreach ($vocabulary as $term) {
            $tf = ($counts[mb_strtolower($term)] ?? 0) / $totalWords;
            $vector[] = (float)$tf;
        }

        return self::normalize($vector);
    }
}
