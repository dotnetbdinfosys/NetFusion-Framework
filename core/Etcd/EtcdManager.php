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

namespace NetFusion\Core\Etcd;

use NetFusion\Core\Etcd\Enums\EventType;
use NetFusion\Core\Etcd\Models\KeyRevision;
use NetFusion\Core\Etcd\Models\Lease;
use NetFusion\Core\Etcd\Models\WatchEvent;
use NetFusion\Core\Etcd\Storage\MvccStore;
use NetFusion\Core\Etcd\Storage\MvccStoreInterface;
use NetFusion\Core\Etcd\Lease\LeaseManager;
use NetFusion\Core\Etcd\Txn\TxnBuilder;
use NetFusion\Core\Etcd\Txn\TxnExecutor;
use NetFusion\Core\Etcd\Watch\Watcher;
use NetFusion\Core\Etcd\Watch\WatchHub;

/**
 * Master Enterprise etcd v3 Facade & Distributed Storage Engine.
 */
class EtcdManager
{
    protected MvccStoreInterface $store;
    protected LeaseManager $leaseManager;
    protected WatchHub $watchHub;
    protected TxnExecutor $txnExecutor;

    public function __construct(
        ?MvccStoreInterface $store = null,
        ?LeaseManager $leaseManager = null,
        ?WatchHub $watchHub = null
    ) {
        $this->store = $store ?? new MvccStore();
        $this->watchHub = $watchHub ?? new WatchHub($this->store);
        $this->leaseManager = $leaseManager ?? new LeaseManager($this->store);

        // Connect lease revocation cascade deletions to the watch hub
        $this->leaseManager->onKeyDeleted(function (string $key, KeyRevision $tomb, ?KeyRevision $prevKv) {
            $this->watchHub->broadcast(new WatchEvent(
                type: EventType::DELETE,
                kv: $tomb,
                prevKv: $prevKv
            ));
        });

        $this->txnExecutor = new TxnExecutor($this->store, $this->leaseManager, $this->watchHub);
    }

    /**
     * Put a key-value pair into the MVCC store.
     */
    public function put(string $key, string $value, int $leaseId = 0, ?KeyRevision &$prevKv = null): KeyRevision
    {
        $kv = $this->store->put($key, $value, $leaseId, $prevKv);

        if ($leaseId > 0) {
            $this->leaseManager->attachKey($leaseId, $key);
        }

        $this->watchHub->broadcast(new WatchEvent(
            type: EventType::PUT,
            kv: $kv,
            prevKv: $prevKv
        ));

        return $kv;
    }

    /**
     * Get a key revision, optionally at a point-in-time historical revision.
     */
    public function get(string $key, int $revision = 0): ?KeyRevision
    {
        return $this->store->get($key, $revision);
    }

    /**
     * Delete a key, writing a tombstone revision and notifying watchers.
     */
    public function delete(string $key, ?KeyRevision &$prevKv = null): ?KeyRevision
    {
        $tomb = $this->store->delete($key, $prevKv);

        if ($tomb !== null) {
            if ($prevKv?->leaseId) {
                $this->leaseManager->detachKey($prevKv->leaseId, $key);
            }

            $this->watchHub->broadcast(new WatchEvent(
                type: EventType::DELETE,
                kv: $tomb,
                prevKv: $prevKv
            ));
        }

        return $tomb;
    }

    /**
     * Delete a range of keys by prefix.
     *
     * @param array<KeyRevision> $deletedKvs
     */
    public function deleteRange(string $prefix, array &$deletedKvs = []): int
    {
        $count = $this->store->deleteRange($prefix, $deletedKvs);

        foreach ($deletedKvs as $tomb) {
            $this->watchHub->broadcast(new WatchEvent(
                type: EventType::DELETE,
                kv: $tomb,
                prevKv: null
            ));
        }

        return $count;
    }

    /**
     * Query a range of keys matching a prefix.
     *
     * @return array<string, KeyRevision>
     */
    public function range(string $prefix, int $revision = 0, int $limit = 0): array
    {
        return $this->store->range($prefix, $revision, $limit);
    }

    /**
     * Compact historical revisions older than the given revision.
     */
    public function compact(int $revision): int
    {
        return $this->store->compact($revision);
    }

    /**
     * Grant a new lease with TTL in seconds.
     */
    public function leaseGrant(int $ttl, int $id = 0): Lease
    {
        return $this->leaseManager->grant($ttl, $id);
    }

    /**
     * Revoke a lease by ID, cascading deletion to all attached keys.
     */
    public function leaseRevoke(int $id): bool
    {
        return $this->leaseManager->revoke($id);
    }

    /**
     * Keep-alive heartbeat for a lease.
     */
    public function leaseKeepAlive(int $id, ?int $ttl = null): int
    {
        return $this->leaseManager->keepAlive($id, $ttl);
    }

    /**
     * Inspect remaining TTL and keys for a lease.
     *
     * @return array<string, mixed>|null
     */
    public function leaseTimeToLive(int $id, bool $listKeys = true): ?array
    {
        return $this->leaseManager->timeToLive($id, $listKeys);
    }

    /**
     * List all active leases.
     *
     * @return array<int, Lease>
     */
    public function leases(): array
    {
        return $this->leaseManager->leases();
    }

    /**
     * Sweep and revoke all expired leases.
     *
     * @return array<int> Revoked lease IDs
     */
    public function checkExpiredLeases(?int $now = null): array
    {
        return $this->leaseManager->checkExpired($now);
    }

    /**
     * Stream events on a key or prefix.
     */
    public function watch(
        string $key,
        callable $callback,
        bool $isPrefix = false,
        int $startRevision = 0,
        bool $prevKv = false
    ): Watcher {
        return $this->watchHub->watch($key, $callback, $isPrefix, $startRevision, $prevKv);
    }

    /**
     * Cancel an active watcher.
     */
    public function cancelWatch(int $watcherId): bool
    {
        return $this->watchHub->cancel($watcherId);
    }

    /**
     * Create a new atomic transaction builder.
     */
    public function txn(): TxnBuilder
    {
        return new TxnBuilder($this->txnExecutor);
    }

    /**
     * Get the current global revision.
     */
    public function revision(): int
    {
        return $this->store->getRevision();
    }

    /**
     * Get the current compaction watermark revision.
     */
    public function compactRevision(): int
    {
        return $this->store->getCompactRevision();
    }

    /**
     * Retrieve revision history of a key.
     *
     * @return array<KeyRevision>
     */
    public function getHistory(string $key, int $startRevision = 0, int $endRevision = 0): array
    {
        return $this->store->getHistory($key, $startRevision, $endRevision);
    }

    /**
     * Access underlying store.
     */
    public function getStore(): MvccStoreInterface
    {
        return $this->store;
    }

    /**
     * Access underlying lease manager.
     */
    public function getLeaseManager(): LeaseManager
    {
        return $this->leaseManager;
    }

    /**
     * Access underlying watch hub.
     */
    public function getWatchHub(): WatchHub
    {
        return $this->watchHub;
    }

    /**
     * Clear all store, lease, and watcher state.
     */
    public function clear(): void
    {
        if ($this->store instanceof MvccStore) {
            $this->store->clear();
        }
        $this->leaseManager->getRegistry()->clear();
        $this->watchHub->clear();
    }
}
