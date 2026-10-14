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

namespace NetFusion\Core\Etcd\Watch;

use NetFusion\Core\Etcd\Enums\EventType;
use NetFusion\Core\Etcd\Exceptions\CompactedRevisionException;
use NetFusion\Core\Etcd\Models\KeyRevision;
use NetFusion\Core\Etcd\Models\WatchEvent;
use NetFusion\Core\Etcd\Storage\MvccStoreInterface;

/**
 * Streaming event watcher hub supporting range matching and historical revision replay.
 */
class WatchHub
{
    /** @var array<int, Watcher> */
    protected array $watchers = [];
    protected int $nextWatcherId = 1;
    protected MvccStoreInterface $store;

    public function __construct(MvccStoreInterface $store)
    {
        $this->store = $store;
    }

    /**
     * Register a new watcher stream.
     *
     * @param string $key Exact key or prefix
     * @param callable $callback Handler invoked with each WatchEvent
     * @param bool $isPrefix Match as prefix instead of exact key
     * @param int $startRevision Replay historical events from this revision (0 for only new events)
     * @param bool $prevKv Include previous key revision in events
     */
    public function watch(
        string $key,
        callable $callback,
        bool $isPrefix = false,
        int $startRevision = 0,
        bool $prevKv = false
    ): Watcher {
        $id = $this->nextWatcherId++;
        $watcher = new Watcher(
            id: $id,
            key: $key,
            isPrefix: $isPrefix,
            startRevision: $startRevision,
            prevKv: $prevKv,
            callback: $callback
        );

        $this->watchers[$id] = $watcher;

        // Historical revision replay if requested
        if ($startRevision > 0) {
            $this->replayHistory($watcher, $startRevision);
        }

        return $watcher;
    }

    /**
     * Cancel an active watcher by ID.
     */
    public function cancel(int $watcherId): bool
    {
        if (isset($this->watchers[$watcherId])) {
            $this->watchers[$watcherId]->cancel();
            unset($this->watchers[$watcherId]);
            return true;
        }

        return false;
    }

    /**
     * Broadcast an event to all matching active watchers.
     */
    public function broadcast(WatchEvent $event): void
    {
        $targetKey = $event->kv?->key ?? $event->prevKv?->key ?? '';

        foreach ($this->watchers as $watcher) {
            if ($watcher->matches($targetKey)) {
                $ev = $watcher->prevKv ? $event : new WatchEvent(
                    type: $event->type,
                    kv: $event->kv,
                    prevKv: null,
                    compactRevision: $event->compactRevision
                );
                $watcher->notify($ev);
            }
        }
    }

    /**
     * Replay historical revisions to a new watcher starting from startRevision.
     */
    protected function replayHistory(Watcher $watcher, int $startRevision): void
    {
        if ($this->store->getCompactRevision() > 0 && $startRevision < $this->store->getCompactRevision()) {
            throw new CompactedRevisionException($startRevision, $this->store->getCompactRevision());
        }

        $allRevs = $this->store->getAllRevisions($startRevision);

        foreach ($allRevs as $rev) {
            if (!$watcher->matches($rev->key)) {
                continue;
            }

            $type = $rev->isTombstone ? EventType::DELETE : EventType::PUT;
            $prevKv = null;

            if ($watcher->prevKv) {
                // Find previous revision for this key
                $history = $this->store->getHistory($rev->key, 0, $rev->modRevision - 1);
                if (!empty($history)) {
                    $prevKv = end($history);
                    if ($prevKv->isTombstone) {
                        $prevKv = null;
                    }
                }
            }

            $event = new WatchEvent(
                type: $type,
                kv: $rev,
                prevKv: $prevKv
            );

            $watcher->notify($event);
        }
    }

    /**
     * Get count of active watchers.
     */
    public function watcherCount(): int
    {
        return count($this->watchers);
    }

    /**
     * Remove all watchers.
     */
    public function clear(): void
    {
        foreach ($this->watchers as $watcher) {
            $watcher->cancel();
        }
        $this->watchers = [];
    }
}
