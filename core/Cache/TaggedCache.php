<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Cache/TaggedCache.php
| Module    : Core\Cache
| Namespace : NetFusion\Core\Cache
| Purpose   : High-performance tag-based cache scoping and O(1) batch invalidation.
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
use NetFusion\Core\Cache\Contracts\CacheInterface;
use NetFusion\Core\Cache\Contracts\TaggedCacheInterface;

/**
 * NetFusion Tagged Cache
 *
 * Implements namespace versioning for instantaneous O(1) tag-based invalidation.
 *
 * @package NetFusion\Core\Cache
 * @version 1.0.0
 */
class TaggedCache implements TaggedCacheInterface
{
    /**
     * @param CacheInterface $cache
     * @param array<string>  $tags
     */
    public function __construct(
        protected readonly CacheInterface $cache,
        protected readonly array          $tags,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->taggedKey($key), $default);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        return $this->cache->set($this->taggedKey($key), $value, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        return $this->cache->delete($this->taggedKey($key));
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return $this->cache->has($this->taggedKey($key));
    }

    /**
     * {@inheritDoc}
     */
    public function remember(string $key, int|DateInterval|null $ttl, Closure $callback): mixed
    {
        return $this->cache->remember($this->taggedKey($key), $ttl, $callback);
    }

    /**
     * {@inheritDoc}
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        return $this->cache->rememberForever($this->taggedKey($key), $callback);
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        foreach ($this->tags as $tag) {
            $this->resetTag($tag);
        }

        return true;
    }

    /**
     * Generate fully qualified namespaced key based on tag versions.
     */
    public function taggedKey(string $key): string
    {
        $tagVersions = [];

        foreach ($this->tags as $tag) {
            $tagVersions[] = $tag . ':' . $this->tagId($tag);
        }

        sort($tagVersions);

        return 'tag:' . sha1(implode('|', $tagVersions)) . ':' . $key;
    }

    /**
     * Retrieve the unique version identifier for a tag.
     */
    protected function tagId(string $tag): string
    {
        $tagKey = 'netfusion_tag:' . $tag;
        $id = $this->cache->get($tagKey);

        if (!$id) {
            $id = bin2hex(random_bytes(8));
            $this->cache->rememberForever($tagKey, fn () => $id);
        }

        return (string) $id;
    }

    /**
     * Invalidate tag by updating its version identifier.
     */
    protected function resetTag(string $tag): void
    {
        $tagKey = 'netfusion_tag:' . $tag;
        $newId = bin2hex(random_bytes(8));

        $this->cache->set($tagKey, $newId, null);
    }
}
