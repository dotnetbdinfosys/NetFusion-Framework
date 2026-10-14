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

namespace NetFusion\Core\DataWarehouse\Cube;

class CubeSlice
{
    /**
     * @param string $cubeName
     * @param array<string, mixed> $predicates Applied slice/dice filters
     * @param array<int, array<string, mixed>> $data Rows resulting from the slice
     * @param array<string, string> $measures Measure definitions
     */
    public function __construct(
        protected string $cubeName,
        protected array $predicates,
        protected array $data,
        protected array $measures = []
    ) {
    }

    public function getCubeName(): string
    {
        return $this->cubeName;
    }

    public function getPredicates(): array
    {
        return $this->predicates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Aggregate measure across slice.
     */
    public function aggregate(string $measure, string $fn = 'SUM'): float
    {
        $sum = 0.0;
        $count = 0;
        $min = null;
        $max = null;

        foreach ($this->data as $row) {
            $val = (float)($row[$measure] ?? 0.0);
            $sum += $val;
            $count++;
            if ($min === null || $val < $min) {
                $min = $val;
            }
            if ($max === null || $val > $max) {
                $max = $val;
            }
        }

        return match (strtoupper($fn)) {
            'SUM' => $sum,
            'AVG', 'AVERAGE' => $count > 0 ? round($sum / $count, 4) : 0.0,
            'MIN' => $min ?? 0.0,
            'MAX' => $max ?? 0.0,
            'COUNT' => (float)$count,
            default => $sum,
        };
    }
}
