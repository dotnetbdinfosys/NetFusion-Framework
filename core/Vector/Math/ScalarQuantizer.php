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

namespace NetFusion\Core\Vector\Math;

use NetFusion\Core\Vector\Contracts\VectorInterface;

class ScalarQuantizer
{
    /**
     * Quantize a continuous float vector into 8-bit integers (SQ8).
     *
     * @return array{bytes: array<int>, min: float, max: float, dimension: int}
     */
    public static function quantizeSQ8(VectorInterface $vector): array
    {
        $values = $vector->toArray();
        $dim = count($values);
        if ($dim === 0) {
            return ['bytes' => [], 'min' => 0.0, 'max' => 0.0, 'dimension' => 0];
        }

        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        if ($range <= 1e-12) {
            return [
                'bytes' => array_fill(0, $dim, 128),
                'min' => $min,
                'max' => $max,
                'dimension' => $dim,
            ];
        }

        $bytes = [];
        foreach ($values as $val) {
            $normalized = ($val - $min) / $range;
            $quantized = (int)round($normalized * 255.0);
            $bytes[] = max(0, min(255, $quantized));
        }

        return [
            'bytes' => $bytes,
            'min' => $min,
            'max' => $max,
            'dimension' => $dim,
        ];
    }

    /**
     * Dequantize SQ8 bytes back into a Vector.
     *
     * @param array{bytes: array<int>, min: float, max: float, dimension: int} $quantized
     */
    public static function dequantizeSQ8(array $quantized): Vector
    {
        $bytes = $quantized['bytes'] ?? [];
        $min = (float)($quantized['min'] ?? 0.0);
        $max = (float)($quantized['max'] ?? 0.0);
        $range = $max - $min;

        $values = [];
        foreach ($bytes as $b) {
            $values[] = $min + (($b / 255.0) * $range);
        }

        return new Vector($values);
    }

    /**
     * Approximate dot product directly on SQ8 quantized representations.
     *
     * @param array{bytes: array<int>, min: float, max: float, dimension: int} $q1
     * @param array{bytes: array<int>, min: float, max: float, dimension: int} $q2
     */
    public static function dotSQ8(array $q1, array $q2): float
    {
        $dim = $q1['dimension'];
        $b1 = $q1['bytes'];
        $b2 = $q2['bytes'];
        $min1 = $q1['min'];
        $max1 = $q1['max'];
        $min2 = $q2['min'];
        $max2 = $q2['max'];

        $range1 = ($max1 - $min1) / 255.0;
        $range2 = ($max2 - $min2) / 255.0;

        $sum = 0.0;
        for ($i = 0; $i < $dim; $i++) {
            $v1 = $min1 + ($b1[$i] * $range1);
            $v2 = $min2 + ($b2[$i] * $range2);
            $sum += $v1 * $v2;
        }

        return $sum;
    }
}
