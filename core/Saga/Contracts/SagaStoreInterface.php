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

namespace NetFusion\Core\Saga\Contracts;

use NetFusion\Core\Saga\SagaInstance;

interface SagaStoreInterface
{
    /**
     * Persist or update a Saga instance and its step results.
     */
    public function save(SagaInstance $instance): void;

    /**
     * Retrieve a Saga instance by ID.
     */
    public function find(string $id): ?SagaInstance;

    /**
     * List all Saga instances, optionally filtered by status or name.
     *
     * @param array<string, mixed> $filters
     * @return SagaInstance[]
     */
    public function all(array $filters = []): array;

    /**
     * Delete a Saga record.
     */
    public function delete(string $id): void;
}
