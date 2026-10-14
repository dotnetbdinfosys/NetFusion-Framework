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

use NetFusion\Core\Etcd\Exceptions\CompactedRevisionException;
use NetFusion\Core\Etcd\Models\KeyRevision;

/**
 * In-memory high-performance MVCC storage engine following etcd v3 semantics.
 */
class MvccStore implements MvccStoreInterface
{
    /** @var array<string, KeyRevision> Fast lookup of current latest revision per key */
    protected array $keyIndex = [];

    /** @var array<string, array<KeyRevision>> Full historical sequence per key sorted by modRevision */
    protected array $history = [];

    /** @var array<int, KeyRevision> Global log ordered by modification revision */
    protected array $revisionLog = [];

    protected RevisionTracker $tracker;

    public function __construct(?RevisionTracker $tracker = null)
    {
        $this->tracker = $tracker ?? new RevisionTracker();
    }

    /**
     * Store a key-value revision into the MVCC store.
     */
    public function put(string $key, string $value, int $leaseId = 0, ?KeyRevision &$prevKv = null): KeyRevision
    {
        $current = $this->keyIndex[$key] ?? null;
        $prevKv = ($current && !$current->isTombstone) ? $current : null;

        if ($current === null || $current->isTombstone) {
            // New key or resurrecting previously deleted key
            $revNumber = $this->tracker->next();
            $createRevision = $revNumber;
            $modRevision = $revNumber;
            $version = 1;
        } else {
            // Updating existing key
            $revNumber = $this->tracker->next();
            $createRevision = $current->createRevision;
            $modRevision = $revNumber;
            $version = $current->version + 1;
        }

        $newRevision = new KeyRevision(
            key: $key,
            createRevision: $createRevision,
            modRevision: $modRevision,
            version: $version,
            value: $value,
            leaseId: $leaseId,
            isTombstone: false
        );

        $this->keyIndex[$key] = $newRevision;
        $this->history[$key][] = $newRevision;
        $this->revisionLog[$modRevision] = $newRevision;

        return $newRevision;
    }

    /**
     * Retrieve a key revision, optionally at a historical point-in-time revision.
     */
    public function get(string $key, int $revision = 0): ?KeyRevision
    {
        if ($revision > 0) {
            if ($this->tracker->isCompacted($revision)) {
                throw new CompactedRevisionException($revision, $this->tracker->compactRevision());
            }

            $keyHistory = $this->history[$key] ?? [];
            if (empty($keyHistory)) {
                return null;
            }

            // Search backwards for latest revision where modRevision <= revision
            for ($i = count($keyHistory) - 1; $i >= 0; $i--) {
                $rev = $keyHistory[$i];
                if ($rev->modRevision <= $revision) {
                    return $rev->isTombstone ? null : $rev;
                }
            }

            return null;
        }

        // Return current latest
        $current = $this->keyIndex[$key] ?? null;
        if ($current === null || $current->isTombstone) {
            return null;
        }

        return $current;
    }

    /**
     * Delete a key, writing a tombstone revision to preserve history.
     */
    public function delete(string $key, ?KeyRevision &$prevKv = null): ?KeyRevision
    {
        $current = $this->keyIndex[$key] ?? null;
        if ($current === null || $current->isTombstone) {
            $prevKv = null;
            return null;
        }

        $prevKv = $current;
        $modRevision = $this->tracker->next();

        $tombstone = new KeyRevision(
            key: $key,
            createRevision: $current->createRevision,
            modRevision: $modRevision,
            version: 0,
            value: null,
            leaseId: 0,
            isTombstone: true
        );

        $this->keyIndex[$key] = $tombstone;
        $this->history[$key][] = $tombstone;
        $this->revisionLog[$modRevision] = $tombstone;

        return $tombstone;
    }

    /**
     * Delete a range of keys matching a prefix.
     *
     * @param array<KeyRevision> $deletedKvs
     */
    public function deleteRange(string $prefix, array &$deletedKvs = []): int
    {
        $deletedKvs = [];
        $count = 0;

        foreach ($this->keyIndex as $key => $rev) {
            if (!$rev->isTombstone && str_starts_with($key, $prefix)) {
                $prev = null;
                $tomb = $this->delete($key, $prev);
                if ($tomb !== null) {
                    $deletedKvs[] = $tomb;
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Query a range of keys by prefix, optionally at a point-in-time revision.
     *
     * @return array<string, KeyRevision>
     */
    public function range(string $prefix, int $revision = 0, int $limit = 0): array
    {
        if ($revision > 0 && $this->tracker->isCompacted($revision)) {
            throw new CompactedRevisionException($revision, $this->tracker->compactRevision());
        }

        $results = [];

        if ($revision === 0) {
            foreach ($this->keyIndex as $key => $rev) {
                if (!$rev->isTombstone && ($prefix === '' || str_starts_with($key, $prefix))) {
                    $results[$key] = $rev;
                }
            }
        } else {
            foreach ($this->history as $key => $keyHistory) {
                if ($prefix !== '' && !str_starts_with($key, $prefix)) {
                    continue;
                }

                for ($i = count($keyHistory) - 1; $i >= 0; $i--) {
                    $rev = $keyHistory[$i];
                    if ($rev->modRevision <= $revision) {
                        if (!$rev->isTombstone) {
                            $results[$key] = $rev;
                        }
                        break;
                    }
                }
            }
        }

        ksort($results);

        if ($limit > 0 && count($results) > $limit) {
            $results = array_slice($results, 0, $limit, true);
        }

        return $results;
    }

    /**
     * Discard historical revisions older than the given revision.
     */
    public function compact(int $revision): int
    {
        $compactRev = $this->tracker->compact($revision);

        // Prune revisionLog
        foreach (array_keys($this->revisionLog) as $rev) {
            if ($rev < $compactRev) {
                unset($this->revisionLog[$rev]);
            }
        }

        // Prune history per key
        foreach ($this->history as $key => $keyHistory) {
            $retained = [];
            $keepLatestOlder = null;

            foreach ($keyHistory as $rev) {
                if ($rev->modRevision < $compactRev) {
                    $keepLatestOlder = $rev;
                } else {
                    $retained[] = $rev;
                }
            }

            // Retain the single latest revision before compactRev if no newer revision exists
            if ($keepLatestOlder !== null && empty($retained)) {
                $retained[] = $keepLatestOlder;
            }

            if (empty($retained) || (count($retained) === 1 && $retained[0]->isTombstone)) {
                // If only tombstone remains older than compactRev, remove key entirely
                if (isset($this->keyIndex[$key]) && $this->keyIndex[$key]->isTombstone) {
                    unset($this->keyIndex[$key]);
                }
                unset($this->history[$key]);
            } else {
                $this->history[$key] = $retained;
            }
        }

        return $compactRev;
    }

    /**
     * Get the current global revision.
     */
    public function getRevision(): int
    {
        return $this->tracker->current();
    }

    /**
     * Get the current compaction watermark revision.
     */
    public function getCompactRevision(): int
    {
        return $this->tracker->compactRevision();
    }

    /**
     * Retrieve historical revisions of a specific key.
     *
     * @return array<KeyRevision>
     */
    public function getHistory(string $key, int $startRevision = 0, int $endRevision = 0): array
    {
        $history = $this->history[$key] ?? [];
        if (empty($history)) {
            return [];
        }

        $filtered = [];
        foreach ($history as $rev) {
            if ($startRevision > 0 && $rev->modRevision < $startRevision) {
                continue;
            }
            if ($endRevision > 0 && $rev->modRevision > $endRevision) {
                continue;
            }
            $filtered[] = $rev;
        }

        return $filtered;
    }

    /**
     * Retrieve all key revisions modified at or after a specific revision.
     *
     * @return array<KeyRevision>
     */
    public function getAllRevisions(int $startRevision = 0): array
    {
        $result = [];
        foreach ($this->revisionLog as $revNum => $keyRev) {
            if ($revNum >= $startRevision) {
                $result[] = $keyRev;
            }
        }

        return $result;
    }

    /**
     * Clear all store contents.
     */
    public function clear(): void
    {
        $this->keyIndex = [];
        $this->history = [];
        $this->revisionLog = [];
        $this->tracker = new RevisionTracker();
    }
}
