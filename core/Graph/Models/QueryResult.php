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

namespace NetFusion\Core\Graph\Models;

class QueryResult
{
    /**
     * @param array<array<string, mixed>> $records
     * @param array<string> $columns
     * @param float $executionTimeMs
     * @param array<string, int> $stats e.g. ['nodes_created' => 0, 'edges_created' => 0, ...]
     */
    public function __construct(
        protected array $records = [],
        protected array $columns = [],
        protected float $executionTimeMs = 0.0,
        protected array $stats = []
    ) {
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @return array<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getExecutionTimeMs(): float
    {
        return $this->executionTimeMs;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function isEmpty(): bool
    {
        return empty($this->records);
    }

    public function first(): ?array
    {
        return $this->records[0] ?? null;
    }

    /**
     * Pluck a single column array from records.
     *
     * @param string $column
     * @return array
     */
    public function pluck(string $column): array
    {
        return array_column($this->records, $column);
    }

    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'records' => $this->records,
            'count' => $this->count(),
            'execution_time_ms' => $this->executionTimeMs,
            'stats' => $this->stats,
        ];
    }
}
