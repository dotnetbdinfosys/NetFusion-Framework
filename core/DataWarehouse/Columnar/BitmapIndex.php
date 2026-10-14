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

class BitmapIndex
{
    /**
     * @var array<string, string> Key: Stringified distinct value, Value: Binary bit string (e.g. "100101")
     */
    protected array $bitmaps = [];

    protected int $totalRows = 0;

    /**
     * Build bitmap index from an array of column values.
     *
     * @param array<int, mixed> $values
     */
    public function build(array $values): void
    {
        $this->totalRows = count($values);
        $this->bitmaps = [];

        // Identify unique values
        $uniqueValues = array_unique($values, SORT_REGULAR);

        foreach ($uniqueValues as $val) {
            $key = $this->serializeValue($val);
            $bitString = '';
            foreach ($values as $rowVal) {
                $bitString .= ($rowVal === $val) ? '1' : '0';
            }
            $this->bitmaps[$key] = $bitString;
        }
    }

    /**
     * Evaluate matching row indices for a specific value.
     *
     * @param mixed $value
     * @return array<int> Matching zero-based row indices
     */
    public function queryEquals(mixed $value): array
    {
        $key = $this->serializeValue($value);
        if (!isset($this->bitmaps[$key])) {
            return [];
        }

        $bitString = $this->bitmaps[$key];
        $matches = [];
        $len = strlen($bitString);

        for ($i = 0; $i < $len; $i++) {
            if ($bitString[$i] === '1') {
                $matches[] = $i;
            }
        }

        return $matches;
    }

    /**
     * Bitwise AND of multiple bitmap match sets.
     *
     * @param array<int> $setA
     * @param array<int> $setB
     * @return array<int>
     */
    public static function intersect(array $setA, array $setB): array
    {
        return array_values(array_intersect($setA, $setB));
    }

    /**
     * Bitwise OR of multiple bitmap match sets.
     *
     * @param array<int> $setA
     * @param array<int> $setB
     * @return array<int>
     */
    public static function union(array $setA, array $setB): array
    {
        return array_values(array_unique(array_merge($setA, $setB)));
    }

    public function getCardinality(): int
    {
        return count($this->bitmaps);
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    protected function serializeValue(mixed $val): string
    {
        if ($val === null) {
            return '__NULL__';
        }
        return is_scalar($val) ? (string)$val : serialize($val);
    }
}
