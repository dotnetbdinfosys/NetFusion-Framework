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

class RunLengthCodec implements CompressionCodecInterface
{
    public function getName(): string
    {
        return 'rle';
    }

    /**
     * Compress array of values using Run-Length Encoding.
     * Output format: [ ['val' => $value, 'count' => $runLength], ... ]
     *
     * @param array<int, mixed> $values
     * @return array<int, array{val: mixed, count: int}>
     */
    public function compress(array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $runs = [];
        $currentVal = $values[0];
        $currentCount = 0;

        foreach ($values as $val) {
            if ($val === $currentVal) {
                $currentCount++;
            } else {
                $runs[] = ['val' => $currentVal, 'count' => $currentCount];
                $currentVal = $val;
                $currentCount = 1;
            }
        }

        if ($currentCount > 0) {
            $runs[] = ['val' => $currentVal, 'count' => $currentCount];
        }

        return $runs;
    }

    /**
     * Decompress RLE stream back into flat array of values.
     *
     * @param mixed $compressed
     * @return array<int, mixed>
     */
    public function decompress(mixed $compressed): array
    {
        if (!is_array($compressed)) {
            return [];
        }

        $decompressed = [];
        foreach ($compressed as $run) {
            $val = $run['val'] ?? null;
            $count = (int)($run['count'] ?? 0);
            for ($i = 0; $i < $count; $i++) {
                $decompressed[] = $val;
            }
        }

        return $decompressed;
    }
}
