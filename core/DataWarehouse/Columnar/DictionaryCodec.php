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

class DictionaryCodec implements CompressionCodecInterface
{
    public function getName(): string
    {
        return 'dictionary';
    }

    /**
     * Compress array of string/mixed values using Dictionary Encoding.
     * Output format: [ 'dictionary' => array of unique values, 'indices' => array of integer offsets ]
     *
     * @param array<int, mixed> $values
     * @return array{dictionary: array<int, mixed>, indices: array<int, int>}
     */
    public function compress(array $values): array
    {
        $dictionary = [];
        $lookup = [];
        $indices = [];
        $dictIdx = 0;

        foreach ($values as $val) {
            $key = is_scalar($val) ? (string)$val : serialize($val);
            if (!isset($lookup[$key])) {
                $lookup[$key] = $dictIdx;
                $dictionary[$dictIdx] = $val;
                $dictIdx++;
            }
            $indices[] = $lookup[$key];
        }

        return [
            'dictionary' => $dictionary,
            'indices' => $indices,
        ];
    }

    /**
     * Decompress dictionary payload back to flat array.
     *
     * @param mixed $compressed
     * @return array<int, mixed>
     */
    public function decompress(mixed $compressed): array
    {
        if (!is_array($compressed) || !isset($compressed['dictionary'], $compressed['indices'])) {
            return [];
        }

        $dictionary = (array)$compressed['dictionary'];
        $indices = (array)$compressed['indices'];

        $decompressed = [];
        foreach ($indices as $idx) {
            $decompressed[] = $dictionary[$idx] ?? null;
        }

        return $decompressed;
    }
}
