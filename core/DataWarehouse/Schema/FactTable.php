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

namespace NetFusion\Core\DataWarehouse\Schema;

use NetFusion\Core\DataWarehouse\Contracts\FactTableInterface;
use NetFusion\Core\DataWarehouse\Columnar\ColumnarTable;

class FactTable implements FactTableInterface
{
    protected ColumnarTable $columnStore;

    /**
     * @param string $name Fact table name (e.g. 'fact_sales', 'fact_orders')
     * @param array<string, string> $dimensionKeys Foreign Key => Dimension Name (e.g. ['customer_sk' => 'dim_customer', 'date_sk' => 'dim_date'])
     * @param array<string, string> $measures Measure Name => Default Aggregation (e.g. ['sales_amount' => 'SUM', 'quantity' => 'SUM', 'discount' => 'AVG'])
     * @param array<string, array{type: string, codec?: string}> $additionalColumns
     */
    public function __construct(
        protected string $name,
        protected array $dimensionKeys = [],
        protected array $measures = [],
        protected array $additionalColumns = []
    ) {
        $this->initColumnStore();
    }

    protected function initColumnStore(): void
    {
        $schema = [];

        // Dimension FKs as integers
        foreach ($this->dimensionKeys as $fk => $dimName) {
            $schema[$fk] = ['type' => 'int', 'codec' => 'bit_packing'];
        }

        // Measures as floats or ints
        foreach ($this->measures as $measure => $agg) {
            $schema[$measure] = ['type' => 'float', 'codec' => 'bit_packing'];
        }

        // Additional columns
        foreach ($this->additionalColumns as $col => $def) {
            $schema[$col] = $def;
        }

        $this->columnStore = new ColumnarTable($this->name, $schema);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDimensionKeys(): array
    {
        return $this->dimensionKeys;
    }

    public function getMeasures(): array
    {
        return $this->measures;
    }

    public function getColumnStore(): ColumnarTable
    {
        return $this->columnStore;
    }

    public function record(array $fact): void
    {
        $this->columnStore->insert($fact);
    }

    public function recordBatch(array $facts): void
    {
        $this->columnStore->insertBatch($facts);
    }

    public function count(): int
    {
        return $this->columnStore->count();
    }

    /**
     * Query fact table directly.
     *
     * @param array<string, mixed> $filters
     * @param array<string> $groupBy
     * @param array<string, string> $aggregations
     * @return array<int, array<string, mixed>>
     */
    public function query(array $filters = [], array $groupBy = [], array $aggregations = []): array
    {
        if (empty($aggregations)) {
            $aggregations = $this->measures;
        }

        return $this->columnStore->query($filters, $groupBy, $aggregations);
    }
}
