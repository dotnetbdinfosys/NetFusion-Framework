<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Scheduling/EventMutex.php
| Module    : Core\Console\Scheduling
| Namespace : NetFusion\Core\Console\Scheduling
| Purpose   : Mutex locking mechanism to prevent overlapping scheduled task executions.
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

namespace NetFusion\Core\Console\Scheduling;

use NetFusion\Core\Application;

/**
 * NetFusion Event Mutex
 *
 * Implements atomic lock checks across cache or filesystem to prevent concurrent runs.
 *
 * @package NetFusion\Core\Console\Scheduling
 * @version 1.0.0
 */
class EventMutex
{
    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app,
    ) {}

    /**
     * Attempt to acquire a mutex lock for the scheduled event.
     *
     * @param  Event $event
     * @param  int   $expiresMinutes
     * @return bool  True if lock was acquired, false if already locked.
     */
    public function create(Event $event, int $expiresMinutes = 1440): bool
    {
        $key = 'schedule_mutex_' . $event->mutexName();

        if ($this->app->bound('cache')) {
            $cache = $this->app->make('cache');
            if ($cache->has($key)) {
                return false;
            }
            return $cache->set($key, time(), $expiresMinutes * 60);
        }

        // Filesystem fallback
        $lockFile = $this->lockFilePath($event);
        if (file_exists($lockFile)) {
            $mtime = filemtime($lockFile);
            if ($mtime !== false && (time() - $mtime) < ($expiresMinutes * 60)) {
                return false;
            }
        }

        $dir = dirname($lockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return @file_put_contents($lockFile, (string) time()) !== false;
    }

    /**
     * Check if a mutex lock currently exists.
     */
    public function exists(Event $event): bool
    {
        $key = 'schedule_mutex_' . $event->mutexName();

        if ($this->app->bound('cache')) {
            return $this->app->make('cache')->has($key);
        }

        $lockFile = $this->lockFilePath($event);
        return file_exists($lockFile);
    }

    /**
     * Release the mutex lock.
     */
    public function forget(Event $event): bool
    {
        $key = 'schedule_mutex_' . $event->mutexName();

        if ($this->app->bound('cache')) {
            return $this->app->make('cache')->delete($key);
        }

        $lockFile = $this->lockFilePath($event);
        if (file_exists($lockFile)) {
            return @unlink($lockFile);
        }

        return true;
    }

    /**
     * Get the fallback lock file path for the event.
     */
    protected function lockFilePath(Event $event): string
    {
        return $this->app->storagePath('framework/schedule/' . $event->mutexName() . '.lock');
    }
}
