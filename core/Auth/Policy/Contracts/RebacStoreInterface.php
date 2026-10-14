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

namespace NetFusion\Core\Auth\Policy\Contracts;

use NetFusion\Core\Auth\Policy\Rebac\RebacTuple;

interface RebacStoreInterface
{
    /**
     * Add or register a relationship tuple.
     */
    public function write(RebacTuple $tuple): void;

    /**
     * Delete a relationship tuple.
     */
    public function delete(RebacTuple $tuple): void;

    /**
     * Read tuples matching criteria.
     *
     * @param string|null $entityType
     * @param string|null $entityId
     * @param string|null $relation
     * @param string|null $subjectType
     * @param string|null $subjectId
     * @return RebacTuple[]
     */
    public function read(
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $relation = null,
        ?string $subjectType = null,
        ?string $subjectId = null
    ): array;

    /**
     * Check if a specific relationship tuple exists.
     */
    public function exists(RebacTuple $tuple): bool;

    /**
     * Find tuples by associative criteria array.
     *
     * @param array<string, string|null> $criteria
     * @return RebacTuple[]
     */
    public function find(array $criteria = []): array;

    /**
     * Clear all relationship tuples.
     */
    public function clear(): void;
}
