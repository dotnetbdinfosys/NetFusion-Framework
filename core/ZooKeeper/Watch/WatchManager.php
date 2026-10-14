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
 * Manages one-time reactive watcher registrations and event dispatching.
 */
class WatchManager
{
    /** @var array<string, array<Watcher>> [path => [Watcher]] */
    protected array $dataWatchers = [];

    /** @var array<string, array<Watcher>> [path => [Watcher]] */
    protected array $childrenWatchers = [];

    /** @var array<string, array<Watcher>> [path => [Watcher]] */
    protected array $existsWatchers = [];

    public function addDataWatch(string $path, callable $callback, int $sessionId = 0): void
    {
        $normPath = '/' . trim($path, '/');
        $this->dataWatchers[$normPath][] = new Watcher($normPath, 'data', $callback, $sessionId);
    }

    public function addChildrenWatch(string $path, callable $callback, int $sessionId = 0): void
    {
        $normPath = '/' . trim($path, '/');
        $this->childrenWatchers[$normPath][] = new Watcher($normPath, 'children', $callback, $sessionId);
    }

    public function addExistsWatch(string $path, callable $callback, int $sessionId = 0): void
    {
        $normPath = '/' . trim($path, '/');
        $this->existsWatchers[$normPath][] = new Watcher($normPath, 'exists', $callback, $sessionId);
    }

    /**
     * Trigger and consume (one-time) watchers matching the event.
     */
    public function trigger(WatcherEvent $event, string $path): void
    {
        $normPath = '/' . trim($path, '/');

        switch ($event) {
            case WatcherEvent::NODE_CREATED:
                // Triggers exists watchers
                if (isset($this->existsWatchers[$normPath])) {
                    $watchers = $this->existsWatchers[$normPath];
                    unset($this->existsWatchers[$normPath]);
                    foreach ($watchers as $w) {
                        $w->trigger($event, $normPath);
                    }
                }
                break;

            case WatcherEvent::NODE_DATA_CHANGED:
                // Triggers data watchers and exists watchers
                if (isset($this->dataWatchers[$normPath])) {
                    $watchers = $this->dataWatchers[$normPath];
                    unset($this->dataWatchers[$normPath]);
                    foreach ($watchers as $w) {
                        $w->trigger($event, $normPath);
                    }
                }
                if (isset($this->existsWatchers[$normPath])) {
                    $watchers = $this->existsWatchers[$normPath];
                    unset($this->existsWatchers[$normPath]);
                    foreach ($watchers as $w) {
                        $w->trigger($event, $normPath);
                    }
                }
                break;

            case WatcherEvent::NODE_CHILDREN_CHANGED:
                // Triggers children watchers on parent path
                if (isset($this->childrenWatchers[$normPath])) {
                    $watchers = $this->childrenWatchers[$normPath];
                    unset($this->childrenWatchers[$normPath]);
                    foreach ($watchers as $w) {
                        $w->trigger($event, $normPath);
                    }
                }
                break;

            case WatcherEvent::NODE_DELETED:
                // Triggers data, exists and children watchers
                if (isset($this->dataWatchers[$normPath])) {
                    $watchers = $this->dataWatchers[$normPath];
                    unset($this->dataWatchers[$normPath]);
                    foreach ($watchers as $w) {
                        $w->trigger($event, $normPath);
                    }
                }
                if (isset($this->existsWatchers[$normPath])) {
                    $watchers = $this->existsWatchers[$normPath];
                    unset($this->existsWatchers[$normPath]);
                    foreach ($watchers as $w) {
                        $w->trigger($event, $normPath);
                    }
                }
                break;

            default:
                break;
        }
    }

    public function clear(): void
    {
        $this->dataWatchers = [];
        $this->childrenWatchers = [];
        $this->existsWatchers = [];
    }
}
