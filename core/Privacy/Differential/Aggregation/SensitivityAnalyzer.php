<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Aggregation/SensitivityAnalyzer.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Privacy\Differential\Aggregation;

/**
 * Calculates global sensitivities and clips individual contributions.
 */
class SensitivityAnalyzer
{
    /**
     * L1 Sensitivity for COUNT query is always 1 (adding or removing 1 row changes count by at most 1).
     */
    public static function countSensitivity(): float
    {
        return 1.0;
    }

    /**
     * L1 Sensitivity for SUM query with bounded values [lower, upper]:
     * Sensitivity = max(|lower|, |upper|)
     */
    public static function sumSensitivity(float $lowerBound, float $upperBound): float
    {
        return max(abs($lowerBound), abs($upperBound));
    }

    /**
     * L2 Norm of a numeric vector: sqrt(sum(x_i^2))
     *
     * @param float[]|int[] $vector
     */
    public static function l2Norm(array $vector): float
    {
        $sumSq = 0.0;
        foreach ($vector as $val) {
            $sumSq += ((float)$val) ** 2;
        }
        return sqrt($sumSq);
    }

    /**
     * Clip a numeric value to range [lower, upper].
     */
    public static function clip(float $val, float $lower, float $upper): float
    {
        return max($lower, min($upper, $val));
    }

    /**
     * Clip a vector by L2 norm to a maximum threshold C:
     * v_clipped = v * min(1, C / ||v||_2)
     *
     * @param float[]|int[] $vector
     * @param float $maxNorm C
     * @return float[]
     */
    public static function clipVector(array $vector, float $maxNorm): array
    {
        $norm = self::l2Norm($vector);
        if ($norm <= 0.0 || $norm <= $maxNorm) {
            return array_map(fn($x) => (float)$x, $vector);
        }

        $scale = $maxNorm / $norm;
        return array_map(fn($x) => (float)$x * $scale, $vector);
    }
}
