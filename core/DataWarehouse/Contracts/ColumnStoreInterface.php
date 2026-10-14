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

namespace NetFusion\Core\DataWarehouse\Contracts;

interface ColumnStoreInterface
{
    /**
     * Get the table name.
     */
    public function getName(): string;

    /**
     * Append a row of data to the columnar store.
     *
     * @param array<string, mixed> $row
     */
    public function insert(array $row): void;

    /**
     * Bulk insert rows of data.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertBatch(array $rows): void;

    /**
     * Execute vectorized query with filters, groupings, and aggregations.
     *
     * @param array<string, mixed> $filters Filter predicates (e.g. ['column' => ['operator' => '=', 'value' => 10]])
     * @param array<string> $groupBy Columns to group by
     * @param array<string, string> $aggregations Measure aggregations (e.g. ['amount' => 'SUM', 'qty' => 'AVG'])
     * @return array<int, array<string, mixed>>
     */
    public function query(array $filters = [], array $groupBy = [], array $aggregations = []): array;

    /**
     * Get total row count in the columnar store.
     */
    public function count(): int;
}
