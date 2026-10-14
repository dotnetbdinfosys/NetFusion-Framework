<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/Contracts/CacheStoreInterface.php
| Module    : Core\Cache\Contracts
| Namespace : NetFusion\Core\Cache\Contracts
| Purpose   : Contract for underlying key-value cache storage drivers.
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

/**
 * NetFusion Cache Store Interface
 *
 * Defines the contract that every low-level cache storage driver must implement.
 *
 * @package NetFusion\Core\Cache\Contracts
 * @version 1.0.0
 */
interface CacheStoreInterface
{
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string $key
     * @return mixed  Returns null if item does not exist or is expired.
     */
    public function get(string $key): mixed;

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string   $key
     * @param  mixed    $value
     * @param  int|null $ttl   Time to live in seconds (null = forever).
     * @return bool
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool;

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
     * Store an item in the cache indefinitely.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Remove an item from the cache.
     *
     * @param  string $key
     * @return bool
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the cache store.
     *
     * @return bool
     */
    public function flush(): bool;

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix(): string;
}
