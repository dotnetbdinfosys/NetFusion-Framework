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

interface DimensionInterface
{
    /**
     * Get dimension table name.
     */
    public function getName(): string;

    /**
     * Get primary surrogate key column name.
     */
    public function getPrimaryKey(): string;

    /**
     * Get natural business key column name.
     */
    public function getNaturalKey(): string;

    /**
     * Get hierarchy levels (e.g. ['year', 'quarter', 'month', 'day']).
     *
     * @return array<string>
     */
    public function getHierarchy(): array;

    /**
     * Upsert a dimension member respecting SCD Type (Type 1 or Type 2).
     *
     * @param array<string, mixed> $attributes
     * @return int Surrogate key ID
     */
    public function upsert(array $attributes): int;

    /**
     * Find dimension member by surrogate key.
     *
     * @param int $surrogateKey
     * @return array<string, mixed>|null
     */
    public function find(int $surrogateKey): ?array;
}
