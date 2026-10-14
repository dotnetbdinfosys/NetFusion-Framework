<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/CacheManager.php
| Module    : Core\Cache
| Namespace : NetFusion\Core\Cache
| Purpose   : Central manager resolving multi-store cache repositories.
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

namespace NetFusion\Core\Cache;

use Closure;
use DateInterval;
use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Cache\Contracts\CacheInterface;
use NetFusion\Core\Cache\Contracts\TaggedCacheInterface;
use NetFusion\Core\Cache\Stores\ArrayStore;
use NetFusion\Core\Cache\Stores\DatabaseStore;
use NetFusion\Core\Cache\Stores\FileStore;
use NetFusion\Core\Database\DatabaseManager;

/**
 * NetFusion Cache Manager
 *
 * Manages cache driver instances and proxies calls to the default store.
 *
 * @package NetFusion\Core\Cache
 * @version 1.0.0
 */
class CacheManager implements CacheInterface
{
    /**
     * Instantiated cache repositories.
     *
     * @var array<string, Repository>
     */
    protected array $stores = [];

    /**
     * Default cache store name.
     */
    protected string $defaultStore;

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app,
    ) {
        $this->defaultStore = (string) config('cache.default', env('CACHE_DRIVER', 'file'));
    }

    /**
     * Get a cache store repository by name.
     *
     * @param  string|null $name
     * @return Repository
     */
    public function store(?string $name = null): Repository
    {
        $name = ($name === null || $name === 'default') ? $this->defaultStore : $name;

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->resolveStore($name);
        }

        return $this->stores[$name];
    }

    /**
     * Alias for store().
     */
    public function driver(?string $name = null): Repository
    {
        return $this->store($name);
    }

    /**
     * Resolve a cache repository instance based on configuration.
     */
    protected function resolveStore(string $name): Repository
    {
        $config = config("cache.stores.{$name}", [
            'driver' => $name,
            'prefix' => config('cache.prefix', 'netfusion_cache_'),
        ]);

        $driver = $config['driver'] ?? $name;
        $prefix = (string) ($config['prefix'] ?? config('cache.prefix', 'netfusion_cache_'));

        $store = match ($driver) {
            'array' => new ArrayStore(prefix: $prefix),
            'file' => new FileStore(
                directory: (string) ($config['path'] ?? $this->app->storagePath('framework/cache/data')),
                prefix: $prefix,
            ),
            'database' => new DatabaseStore(
                connection: $this->app->make(DatabaseManager::class)->connection($config['connection'] ?? null),
                table: (string) ($config['table'] ?? 'netfusion_cache'),
                prefix: $prefix,
            ),
            default => throw new InvalidArgumentException("Unsupported cache driver: [{$driver}]."),
        };

        return new Repository($store);
    }

    /**
     * Set default cache store name.
     */
    public function setDefaultStore(string $name): void
    {
        $this->defaultStore = $name;
    }

    /**
     * Get default cache store name.
     */
    public function getDefaultStore(): string
    {
        return $this->defaultStore;
    }

    // =========================================================================
    //  CacheInterface Proxy Methods
    // =========================================================================

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        return $this->store()->delete($key);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        return $this->store()->clear();
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->store()->getMultiple($keys, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        return $this->store()->setMultiple($values, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->store()->deleteMultiple($keys);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    /**
     * {@inheritDoc}
     */
    public function remember(string $key, int|DateInterval|null $ttl, Closure $callback): mixed
    {
        return $this->store()->remember($key, $ttl, $callback);
    }

    /**
     * {@inheritDoc}
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        return $this->store()->rememberForever($key, $callback);
    }

    /**
     * {@inheritDoc}
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->store()->pull($key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->store()->increment($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->store()->decrement($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function tags(array|string $names): TaggedCacheInterface
    {
        return $this->store()->tags($names);
    }
}
