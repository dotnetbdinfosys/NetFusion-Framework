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

namespace NetFusion\Core\ZooKeeper\Watch;

use NetFusion\Core\ZooKeeper\Enums\WatcherEvent;

/**
 * Reactive Watcher Callback Registration.
 * In ZooKeeper, a watch is a one-time trigger sent to the client that set the watch when the data or children change.
 */
class Watcher
{
    protected string $path;
    protected string $type; // 'data', 'children', 'exists'
    /** @var callable */
    protected $callback;
    protected int $sessionId;

    public function __construct(string $path, string $type, callable $callback, int $sessionId = 0)
    {
        $this->path = '/' . trim($path, '/');
        $this->type = $type;
        $this->callback = $callback;
        $this->sessionId = $sessionId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    public function trigger(WatcherEvent $event, string $eventPath): void
    {
        ($this->callback)($event, $eventPath);
    }
}
