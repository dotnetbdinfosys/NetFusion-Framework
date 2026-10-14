<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Etcd\Lease;

use NetFusion\Core\Etcd\Models\Lease;

/**
 * In-memory registry for tracking and managing distributed leases.
 */
class LeaseRegistry
{
    /** @var array<int, Lease> */
    protected array $leases = [];

    /**
     * Register a lease.
     */
    public function register(Lease $lease): void
    {
        $this->leases[$lease->id] = $lease;
    }

    /**
     * Retrieve a lease by ID.
     */
    public function get(int $leaseId): ?Lease
    {
        return $this->leases[$leaseId] ?? null;
    }

    /**
     * Check if a lease ID exists.
     */
    public function has(int $leaseId): bool
    {
        return isset($this->leases[$leaseId]);
    }

    /**
     * Remove a lease from the registry.
     */
    public function remove(int $leaseId): ?Lease
    {
        $lease = $this->leases[$leaseId] ?? null;
        unset($this->leases[$leaseId]);
        return $lease;
    }

    /**
     * Get all active leases.
     *
     * @return array<int, Lease>
     */
    public function all(): array
    {
        return $this->leases;
    }

    /**
     * Find all expired leases at a given timestamp.
     *
     * @return array<Lease>
     */
    public function findExpired(?int $now = null): array
    {
        $time = $now ?? time();
        $expired = [];

        foreach ($this->leases as $lease) {
            if ($lease->isExpired($time)) {
                $expired[] = $lease;
            }
        }

        return $expired;
    }

    /**
     * Clear all registered leases.
     */
    public function clear(): void
    {
        $this->leases = [];
    }
}
