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

namespace NetFusion\Core\DataWarehouse\Columnar;

use NetFusion\Core\DataWarehouse\Contracts\CompressionCodecInterface;

class BitPackingCodec implements CompressionCodecInterface
{
    public function getName(): string
    {
        return 'bit_packing';
    }

    /**
     * Compress numeric array with frame-of-reference (delta from minimum) and bit packing.
     * Output format: [ 'min' => $min, 'bit_width' => $bits, 'data' => $deltas, 'count' => $count ]
     *
     * @param array<int, mixed> $values
     * @return array{min: float|int, bit_width: int, data: array<int, float|int>, count: int}
     */
    public function compress(array $values): array
    {
        if (empty($values)) {
            return ['min' => 0, 'bit_width' => 0, 'data' => [], 'count' => 0];
        }

        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        $bitWidth = $range > 0 ? (int)ceil(log($range + 1, 2)) : 1;
        $deltas = array_map(fn($v) => $v - $min, $values);

        return [
            'min' => $min,
            'bit_width' => $bitWidth,
            'data' => $deltas,
            'count' => count($values),
        ];
    }

    /**
     * Decompress frame-of-reference encoded numbers.
     *
     * @param mixed $compressed
     * @return array<int, float|int>
     */
    public function decompress(mixed $compressed): array
    {
        if (!is_array($compressed) || !isset($compressed['min'], $compressed['data'])) {
            return [];
        }

        $min = $compressed['min'];
        $deltas = (array)$compressed['data'];

        return array_map(fn($d) => $min + $d, $deltas);
    }
}
