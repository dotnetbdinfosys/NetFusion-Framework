<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/LockManager.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock
| Purpose   : Factory and manager for multi-driver distributed locks.
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

namespace NetFusion\Core\Lock;

use Closure;
use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Lock\Contracts\LockDriverInterface;
use NetFusion\Core\Lock\Contracts\LockInterface;
use NetFusion\Core\Lock\Drivers\ArrayLockDriver;
use NetFusion\Core\Lock\Drivers\DatabaseLockDriver;
use NetFusion\Core\Lock\Drivers\FileAtomicLockDriver;

class LockManager
{
    /**
     * @var array<string, LockDriverInterface>
     */
    protected array $drivers = [];

    /**
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    public function __construct(protected Application $app)
    {
    }

    public function driver(?string $name = null): LockDriverInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    public function getDefaultDriver(): string
    {
        /** @var \NetFusion\Core\Config\Config $config */
        $config = $this->app->make('config');
        return $config->get('lock.default', 'database');
    }

    protected function resolve(string $name): LockDriverInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->app);
        }

        /** @var \NetFusion\Core\Config\Config $config */
        $config = $this->app->make('config');

        return match ($name) {
            'database' => new DatabaseLockDriver(
                $this->app->make(DatabaseManager::class),
                (string)$config->get('lock.table', 'netfusion_locks')
            ),
            'file' => new FileAtomicLockDriver(
                (string)$config->get('lock.storage_path', $this->app->storagePath('framework/locks'))
            ),
            'array', 'in_memory' => new ArrayLockDriver(),
            default => throw new InvalidArgumentException("Unsupported lock driver [{$name}]."),
        };
    }

    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Creates a new named Lock instance.
     */
    public function create(string $name, int $seconds = 60, ?string $owner = null, ?string $driver = null): LockInterface
    {
        return new Lock($this->driver($driver), $name, $seconds, $owner);
    }

    /**
     * Shortcut to acquire lock and run closure.
     */
    public function get(string $name, callable $callback, int $seconds = 60, ?string $driver = null): mixed
    {
        return $this->create($name, $seconds, null, $driver)->get($callback);
    }

    /**
     * Shortcut to block and run closure.
     */
    public function block(string $name, int $waitTimeoutSeconds, callable $callback, int $lockSeconds = 60, ?string $driver = null): mixed
    {
        return $this->create($name, $lockSeconds, null, $driver)->block($waitTimeoutSeconds, $callback);
    }

    public function cleanExpired(?string $driver = null): int
    {
        return $this->driver($driver)->cleanExpired();
    }

    public function listLocks(?string $driver = null): array
    {
        return $this->driver($driver)->all();
    }
}