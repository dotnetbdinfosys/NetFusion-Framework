<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/Contracts/TaggedCacheInterface.php
| Module    : Core\Cache\Contracts
| Namespace : NetFusion\Core\Cache\Contracts
| Purpose   : Contract for tag-scoped cache operations and batch invalidation.
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
 * NetFusion Tagged Cache Interface
 *
 * Scopes cache items under one or more tags, enabling instant multi-item invalidation.
 *
 * @package NetFusion\Core\Cache\Contracts
 * @version 1.0.0
 */
interface TaggedCacheInterface
{
    /**
     * Retrieve an item from the tagged cache.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the tagged cache.
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool;

    /**
     * Delete an item from the tagged cache.
     */
    public function delete(string $key): bool;

    /**
     * Check if an item exists in the tagged cache.
     */
    public function has(string $key): bool;

    /**
     * Remember an item in the tagged cache.
     */
    public function remember(string $key, int|DateInterval|null $ttl, Closure $callback): mixed;

    /**
     * Remember an item forever in the tagged cache.
     */
    public function rememberForever(string $key, Closure $callback): mixed;

    /**
     * Flush all items associated with any of the tags.
     */
    public function flush(): bool;
}
