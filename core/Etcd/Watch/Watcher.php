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

use NetFusion\Core\Etcd\Models\WatchEvent;

/**
 * Represents an active watcher stream on a key or key prefix.
 */
class Watcher
{
    protected bool $active = true;

    /**
     * @param int $id Unique watcher identifier
     * @param string $key Exact key or prefix string to watch
     * @param bool $isPrefix Whether this watcher observes key prefix
     * @param int $startRevision Historical revision to replay from (0 for latest)
     * @param bool $prevKv Include previous key-value state in events
     * @param callable $callback Handler invoked with each WatchEvent
     */
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly bool $isPrefix,
        public readonly int $startRevision,
        public readonly bool $prevKv,
        protected $callback
    ) {
    }

    /**
     * Check if a given key matches this watcher filter.
     */
    public function matches(string $key): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->isPrefix) {
            return $this->key === '' || str_starts_with($key, $this->key);
        }

        return $this->key === $key;
    }

    /**
     * Deliver a watch event to the registered callback.
     */
    public function notify(WatchEvent $event): void
    {
        if ($this->active && ($this->callback !== null)) {
            ($this->callback)($event);
        }
    }

    /**
     * Cancel/close this watcher.
     */
    public function cancel(): void
    {
        $this->active = false;
    }

    /**
     * Determine if this watcher is actively streaming.
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}
