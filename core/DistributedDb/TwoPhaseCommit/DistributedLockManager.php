<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\DistributedDb\TwoPhaseCommit;

/**
 * Distributed lock manager offering lease-based mutual exclusion and deadlock safety.
 */
class DistributedLockManager
{
    /**
     * @var array<string, DistributedLock>
     */
    private array $locks = [];

    private int $globalFencingSequence = 0;

    /**
     * Attempt to acquire a distributed lock on a resource.
     *
     * @param string $resource Resource identifier
     * @param string $owner Lock requester identifier
     * @param int $ttlMs Lease TTL in milliseconds
     * @return DistributedLock|null Acquired lock, or null if contended
     */
    public function acquire(string $resource, string $owner, int $ttlMs = 5000): ?DistributedLock
    {
        $existing = $this->locks[$resource] ?? null;

        if ($existing !== null && !$existing->isExpired()) {
            if ($existing->owner === $owner) {
                // Re-entrant renewal
                $this->renew($existing, $ttlMs);
                return $this->locks[$resource];
            }
            return null; // Contended
        }

        $this->globalFencingSequence++;
        $expiresAt = microtime(true) + ($ttlMs / 1000.0);

        $lock = new DistributedLock(
            resource: $resource,
            owner: $owner,
            fencingToken: $this->globalFencingSequence,
            expiresAt: $expiresAt,
            ttlMs: $ttlMs
        );

        $this->locks[$resource] = $lock;
        return $lock;
    }

    /**
     * Release an acquired distributed lock.
     */
    public function release(DistributedLock $lock): bool
    {
        $existing = $this->locks[$lock->resource] ?? null;

        if ($existing === null) {
            return false;
        }

        if ($existing->owner === $lock->owner && $existing->fencingToken === $lock->fencingToken) {
            unset($this->locks[$lock->resource]);
            return true;
        }

        return false;
    }

    /**
     * Renew lease on an existing lock.
     */
    public function renew(DistributedLock $lock, int $ttlMs = 5000): bool
    {
        $existing = $this->locks[$lock->resource] ?? null;

        if ($existing === null || $existing->owner !== $lock->owner) {
            return false;
        }

        $newExpiresAt = microtime(true) + ($ttlMs / 1000.0);
        $this->locks[$lock->resource] = new DistributedLock(
            resource: $lock->resource,
            owner: $lock->owner,
            fencingToken: $existing->fencingToken,
            expiresAt: $newExpiresAt,
            ttlMs: $ttlMs
        );

        return true;
    }

    /**
     * Execute a callback protected by a distributed resource lock.
     *
     * @template T
     * @param string $resource
     * @param string $owner
     * @param callable(DistributedLock): T $callback
     * @param int $ttlMs
     * @return T
     */
    public function withLock(string $resource, string $owner, callable $callback, int $ttlMs = 5000): mixed
    {
        $lock = $this->acquire($resource, $owner, $ttlMs);
        if ($lock === null) {
            throw new \RuntimeException("Could not acquire distributed lock for resource '{$resource}'.");
        }

        try {
            return $callback($lock);
        } finally {
            $this->release($lock);
        }
    }

    /**
     * @return array<string, DistributedLock>
     */
    public function getActiveLocks(): array
    {
        $active = [];
        foreach ($this->locks as $key => $lock) {
            if (!$lock->isExpired()) {
                $active[$key] = $lock;
            }
        }

        return $active;
    }
}
