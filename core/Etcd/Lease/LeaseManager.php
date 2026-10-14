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

use NetFusion\Core\Etcd\Exceptions\EtcdException;
use NetFusion\Core\Etcd\Models\KeyRevision;
use NetFusion\Core\Etcd\Models\Lease;
use NetFusion\Core\Etcd\Storage\MvccStoreInterface;

/**
 * Manages distributed leases, heartbeats, keep-alive refreshes, and cascade key evictions.
 */
class LeaseManager
{
    protected LeaseRegistry $registry;
    protected MvccStoreInterface $store;

    /** @var callable|null Callback invoked whenever a key is deleted due to lease revocation/expiry */
    protected $onKeyDeleted = null;

    public function __construct(
        MvccStoreInterface $store,
        ?LeaseRegistry $registry = null
    ) {
        $this->store = $store;
        $this->registry = $registry ?? new LeaseRegistry();
    }

    /**
     * Grant a new lease with a TTL in seconds.
     */
    public function grant(int $ttl, int $id = 0): Lease
    {
        if ($ttl <= 0) {
            throw new EtcdException('etcd: lease TTL must be greater than 0');
        }

        if ($id <= 0) {
            // Generate pseudo 64-bit lease ID
            $id = (int) (hrtime(true) ^ (random_int(1000, 9999) << 16));
            if ($id <= 0) {
                $id = abs($id) + 1;
            }
        }

        if ($this->registry->has($id)) {
            throw new EtcdException(sprintf('etcd: lease %d already exists', $id));
        }

        $now = time();
        $lease = new Lease(
            id: $id,
            ttl: $ttl,
            grantedAt: $now,
            expiresAt: $now + $ttl
        );

        $this->registry->register($lease);

        return $lease;
    }

    /**
     * Revoke a lease by ID, cascading deletion to all attached keys.
     */
    public function revoke(int $id): bool
    {
        $lease = $this->registry->get($id);
        if ($lease === null) {
            return false;
        }

        // Cascade delete attached keys
        foreach ($lease->getKeys() as $key) {
            $prev = null;
            $tomb = $this->store->delete($key, $prev);
            if ($tomb !== null && $this->onKeyDeleted !== null) {
                ($this->onKeyDeleted)($key, $tomb, $prev);
            }
        }

        $this->registry->remove($id);

        return true;
    }

    /**
     * Refresh a lease TTL (Keep-Alive).
     */
    public function keepAlive(int $id, ?int $ttl = null): int
    {
        $lease = $this->registry->get($id);
        if ($lease === null || $lease->isExpired()) {
            $this->revoke($id);
            return -1;
        }

        $lease->keepAlive($ttl);
        return $lease->remainingTtl();
    }

    /**
     * Attach a key to an existing lease.
     */
    public function attachKey(int $id, string $key): void
    {
        $lease = $this->registry->get($id);
        if ($lease === null || $lease->isExpired()) {
            throw new EtcdException(sprintf('etcd: requested lease %d not found or expired', $id));
        }

        $lease->attachKey($key);
    }

    /**
     * Detach a key from a lease.
     */
    public function detachKey(int $id, string $key): void
    {
        $lease = $this->registry->get($id);
        if ($lease !== null) {
            $lease->detachKey($key);
        }
    }

    /**
     * Get remaining time-to-live and metadata of a lease.
     *
     * @return array<string, mixed>|null
     */
    public function timeToLive(int $id, bool $listKeys = true): ?array
    {
        $lease = $this->registry->get($id);
        if ($lease === null) {
            return null;
        }

        $data = [
            'id'           => $lease->id,
            'ttl'          => $lease->ttl,
            'grantedAt'    => $lease->grantedAt,
            'expiresAt'    => $lease->getExpiresAt(),
            'remainingTtl' => $lease->remainingTtl(),
        ];

        if ($listKeys) {
            $data['keys'] = $lease->getKeys();
        }

        return $data;
    }

    /**
     * Get all active leases.
     *
     * @return array<int, Lease>
     */
    public function leases(): array
    {
        return $this->registry->all();
    }

    /**
     * Get a specific lease.
     */
    public function getLease(int $id): ?Lease
    {
        return $this->registry->get($id);
    }

    /**
     * Check for expired leases and evict attached keys.
     *
     * @return array<int> IDs of revoked leases
     */
    public function checkExpired(?int $now = null): array
    {
        $expired = $this->registry->findExpired($now);
        $revokedIds = [];

        foreach ($expired as $lease) {
            $this->revoke($lease->id);
            $revokedIds[] = $lease->id;
        }

        return $revokedIds;
    }

    /**
     * Set callback for when attached keys are deleted via revocation or expiry.
     */
    public function onKeyDeleted(?callable $callback): void
    {
        $this->onKeyDeleted = $callback;
    }

    /**
     * Access underlying registry.
     */
    public function getRegistry(): LeaseRegistry
    {
        return $this->registry;
    }
}
