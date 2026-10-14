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

namespace NetFusion\Core\Auth\Policy\Store;

use NetFusion\Core\Auth\Policy\Contracts\PolicyStoreInterface;
use NetFusion\Core\Auth\Policy\Policy;

class ArrayPolicyStore implements PolicyStoreInterface
{
    /** @var array<string, Policy> */
    protected array $policies = [];

    /**
     * @param Policy[] $policies
     */
    public function __construct(array $policies = [])
    {
        foreach ($policies as $p) {
            $this->save($p);
        }
    }

    public function save(Policy $policy): void
    {
        $this->policies[$policy->id] = $policy;
    }

    public function find(string $id): ?Policy
    {
        return $this->policies[$id] ?? null;
    }

    public function get(string $id): ?Policy
    {
        return $this->find($id);
    }

    public function has(string $id): bool
    {
        return isset($this->policies[$id]);
    }

    public function all(): array
    {
        $enabled = array_filter($this->policies, fn(Policy $p) => $p->enabled);
        usort($enabled, fn(Policy $a, Policy $b) => $b->priority <=> $a->priority);
        return array_values($enabled);
    }

    public function delete(string $id): void
    {
        unset($this->policies[$id]);
    }

    public function clear(): void
    {
        $this->policies = [];
    }

    public function count(): int
    {
        return count($this->policies);
    }
}
