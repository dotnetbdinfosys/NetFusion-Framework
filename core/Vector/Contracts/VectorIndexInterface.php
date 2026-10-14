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

namespace NetFusion\Core\Vector\Contracts;

use JsonSerializable;

interface VectorIndexInterface extends JsonSerializable
{
    /**
     * Add a vector to the index.
     *
     * @param string $id Unique identifier
     * @param VectorInterface $vector Vector instance
     * @param array<string, mixed> $metadata Document metadata
     */
    public function insert(string $id, VectorInterface $vector, array $metadata = []): void;

    /**
     * Search nearest neighbors for query vector.
     *
     * @param VectorInterface $query Query vector
     * @param int $k Number of nearest neighbors to retrieve
     * @param array<string, mixed> $filter Metadata filtering criteria
     * @return array<array{id: string, score: float, distance: float, metadata: array<string, mixed>}>
     */
    public function search(VectorInterface $query, int $k = 10, array $filter = []): array;

    /**
     * Delete an item by ID from the index.
     */
    public function delete(string $id): bool;

    /**
     * Check if ID exists in index.
     */
    public function has(string $id): bool;

    /**
     * Get total number of indexed vectors.
     */
    public function count(): int;

    /**
     * Clear the index.
     */
    public function clear(): void;
}
