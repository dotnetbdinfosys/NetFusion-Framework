<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/Contracts/CacheInterface.php
| Module    : Core\Cache\Contracts
| Namespace : NetFusion\Core\Cache\Contracts
| Purpose   : High-level PSR-16 compatible Cache interface with NetFusion extensions.
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

namespace NetFusion\Core\Cache\Contracts;

use Closure;
use DateInterval;

/**
 * NetFusion Cache Interface
 *
 * Provides a PSR-16 compatible simple cache contract enriched with fluent
 * caching helpers such as remember(), pull(), atomic counters, and tagging.
 *
 * @package NetFusion\Core\Cache\Contracts
 * @version 1.0.0
 */
interface CacheInterface
{
    /**
     * Fetches a value from the cache.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL.
     *
     * @param  string                  $key
     * @param  mixed                   $value
     * @param  int|DateInterval|null   $ttl   Seconds or DateInterval (null = forever).
     * @return bool
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool;

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param  string $key
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param  iterable<string> $keys
     * @param  mixed            $default
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param  iterable<string, mixed> $values
     * @param  int|DateInterval|null   $ttl
     * @return bool
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool;

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param  iterable<string> $keys
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param  string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param  string                  $key
     * @param  int|DateInterval|null   $ttl
     * @param  Closure                 $callback
     * @return mixed
     */
    public function remember(string $key, int|DateInterval|null $ttl, Closure $callback): mixed;

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @param  string  $key
     * @param  Closure $callback
     * @return mixed
     */
    public function rememberForever(string $key, Closure $callback): mixed;

    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string $key
     * @param  int    $value
     * @return int|bool
     */
    public function increment(string $key, int $value = 1): int|bool;

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string $key
     * @param  int    $value
     * @return int|bool
     */
    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Begin executing a new tags operation.
     *
     * @param  array<string>|string $names
     * @return TaggedCacheInterface
     */
    public function tags(array|string $names): TaggedCacheInterface;
}
