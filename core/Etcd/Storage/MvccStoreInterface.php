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

namespace NetFusion\Core\Etcd\Storage;

use NetFusion\Core\Etcd\Models\KeyRevision;

/**
 * Contract for Multi-Version Concurrency Control (MVCC) storage engine.
 */
interface MvccStoreInterface
{
    /**
     * Store a key-value revision into the MVCC store.
     */
    public function put(string $key, string $value, int $leaseId = 0, ?KeyRevision &$prevKv = null): KeyRevision;

    /**
     * Retrieve a key revision, optionally at a historical point-in-time revision.
     */
    public function get(string $key, int $revision = 0): ?KeyRevision;

    /**
     * Delete a key, writing a tombstone revision to preserve history.
     */
    public function delete(string $key, ?KeyRevision &$prevKv = null): ?KeyRevision;

    /**
     * Delete a range of keys matching a prefix.
     *
     * @param array<KeyRevision> $deletedKvs
     */
    public function deleteRange(string $prefix, array &$deletedKvs = []): int;

    /**
     * Query a range of keys by prefix, optionally at a point-in-time revision.
     *
     * @return array<string, KeyRevision>
     */
    public function range(string $prefix, int $revision = 0, int $limit = 0): array;

    /**
     * Discard historical revisions older than the given revision.
     */
    public function compact(int $revision): int;

    /**
     * Get the current global revision.
     */
    public function getRevision(): int;

    /**
     * Get the current compaction watermark revision.
     */
    public function getCompactRevision(): int;

    /**
     * Retrieve historical revisions of a specific key.
     *
     * @return array<KeyRevision>
     */
    public function getHistory(string $key, int $startRevision = 0, int $endRevision = 0): array;

    /**
     * Retrieve all key revisions modified at or after a specific revision.
     *
     * @return array<KeyRevision>
     */
    public function getAllRevisions(int $startRevision = 0): array;
}
