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

namespace NetFusion\Core\Saga\Store;

use NetFusion\Core\Saga\Contracts\SagaStoreInterface;
use NetFusion\Core\Saga\SagaInstance;

class ArraySagaStore implements SagaStoreInterface
{
    /** @var array<string, SagaInstance> */
    protected array $instances = [];

    public function save(SagaInstance $instance): void
    {
        $this->instances[$instance->id] = clone $instance;
    }

    public function find(string $id): ?SagaInstance
    {
        if (isset($this->instances[$id])) {
            return clone $this->instances[$id];
        }
        return null;
    }

    public function all(array $filters = []): array
    {
        $results = array_values($this->instances);

        if (isset($filters['status'])) {
            $status = strtoupper((string)$filters['status']);
            $results = array_filter($results, fn(SagaInstance $i) => $i->state->value === $status);
        }

        if (isset($filters['saga_name'])) {
            $results = array_filter($results, fn(SagaInstance $i) => $i->sagaName === $filters['saga_name']);
        }

        return array_values($results);
    }

    public function delete(string $id): void
    {
        unset($this->instances[$id]);
    }

    public function clear(): void
    {
        $this->instances = [];
    }

    public function count(): int
    {
        return count($this->instances);
    }
}
