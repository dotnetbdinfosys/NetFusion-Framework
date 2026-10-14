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

interface FactTableInterface
{
    /**
     * Get fact table name.
     */
    public function getName(): string;

    /**
     * Get dimension foreign keys mapped in this fact table.
     *
     * @return array<string, string> Key: FK column name, Value: Dimension name
     */
    public function getDimensionKeys(): array;

    /**
     * Get measures definition and default aggregation type.
     *
     * @return array<string, string> Key: Measure column name, Value: Aggregation (SUM, AVG, MIN, MAX, COUNT)
     */
    public function getMeasures(): array;

    /**
     * Record a fact row.
     *
     * @param array<string, mixed> $fact
     */
    public function record(array $fact): void;

    /**
     * Record multiple fact rows in batch.
     *
     * @param array<int, array<string, mixed>> $facts
     */
    public function recordBatch(array $facts): void;
}
