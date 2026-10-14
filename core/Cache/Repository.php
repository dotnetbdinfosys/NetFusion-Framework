<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/Repository.php
| Module    : Core\Cache
| Namespace : NetFusion\Core\Cache
| Purpose   : High-level cache repository implementing CacheInterface around any store.
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
use DateTime;
use NetFusion\Core\Cache\Contracts\CacheInterface;
use NetFusion\Core\Cache\Contracts\CacheStoreInterface;
use NetFusion\Core\Cache\Contracts\TaggedCacheInterface;

/**
 * NetFusion Cache Repository
 *
 * Provides a rich, developer-friendly API on top of raw storage drivers.
 *
 * @package NetFusion\Core\Cache
 * @version 1.0.0
 */
class Repository implements CacheInterface
{
    /**
     * @param CacheStoreInterface $store
     */
    public function __construct(
        protected readonly CacheStoreInterface $store,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store->get($key);

        return $value !== null ? $value : ($default instanceof Closure ? $default() : $default);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $seconds = $this->getSeconds($ttl);

        if ($seconds !== null && $seconds <= 0) {
            return $this->delete($key);
        }

        return $this->store->put($key, $value, $seconds);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        return $this->store->forget($key);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        return $this->store->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set((string)$key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return $this->store->get($key) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function remember(string $key, int|DateInterval|null $ttl, Closure $callback): mixed
    {
        $val = $this->get($key);

        if ($val !== null) {
            return $val;
        }

        $val = $callback();
        $this->set($key, $val, $ttl);

        return $val;
    }

    /**
     * {@inheritDoc}
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        $val = $this->get($key);

        if ($val !== null) {
            return $val;
        }

        $val = $callback();
        $this->store->forever($key, $val);

        return $val;
    }

    /**
     * {@inheritDoc}
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $val = $this->get($key, $default);
        $this->delete($key);

        return $val;
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->store->increment($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->store->decrement($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function tags(array|string $names): TaggedCacheInterface
    {
        $tags = is_array($names) ? $names : [$names];

        return new TaggedCache($this, $tags);
    }

    /**
     * Get underlying store instance.
     */
    public function getStore(): CacheStoreInterface
    {
        return $this->store;
    }

    /**
     * Convert TTL representation into seconds.
     */
    protected function getSeconds(int|DateInterval|null $ttl): ?int
    {
        if ($ttl instanceof DateInterval) {
            $now = new DateTime();
            $future = (clone $now)->add($ttl);
            return $future->getTimestamp() - $now->getTimestamp();
        }

        return $ttl;
    }
}
