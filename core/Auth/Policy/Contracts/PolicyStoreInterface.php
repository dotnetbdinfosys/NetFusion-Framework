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

use NetFusion\Core\Auth\Policy\Policy;

interface PolicyStoreInterface
{
    /**
     * Save or update a policy.
     */
    public function save(Policy $policy): void;

    /**
     * Find a policy by ID.
     */
    public function find(string $id): ?Policy;

    /**
     * Get a policy by ID (alias of find).
     */
    public function get(string $id): ?Policy;

    /**
     * Check if a policy exists by ID.
     */
    public function has(string $id): bool;

    /**
     * List all enabled policies ordered by priority descending.
     *
     * @return Policy[]
     */
    public function all(): array;

    /**
     * Delete a policy by ID.
     */
    public function delete(string $id): void;
}
