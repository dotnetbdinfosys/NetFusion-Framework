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

namespace NetFusion\Core\Edge\Cache;

use NetFusion\Core\Edge\Http\EdgeRequest;
use NetFusion\Core\Edge\Http\EdgeResponse;

/**
 * Web Cache API Compliant Edge Cache Storage.
 * Provides sub-millisecond edge KV response caching, surrogate key tag purging, and TTL management.
 */
class EdgeCache
{
    /** @var array<string, array{response: EdgeResponse, expiresAt: float, tags: array<string>}> */
    protected array $store = [];
    protected int $hits = 0;
    protected int $misses = 0;

    /**
     * Store a response in the edge cache.
     *
     * @param array<string> $tags Surrogate keys for targeted purge
     */
    public function put(EdgeRequest|string $request, EdgeResponse $response, int $ttlSeconds = 300, array $tags = []): void
    {
        $key = $this->resolveKey($request);
        $this->store[$key] = [
            'response' => $response,
            'expiresAt' => microtime(true) + $ttlSeconds,
            'tags' => $tags,
        ];
    }

    /**
     * Retrieve a cached response.
     */
    public function match(EdgeRequest|string $request): ?EdgeResponse
    {
        $key = $this->resolveKey($request);

        if (!isset($this->store[$key])) {
            $this->misses++;
            return null;
        }

        $entry = $this->store[$key];

        // Check expiration
        if (microtime(true) > $entry['expiresAt']) {
            unset($this->store[$key]);
            $this->misses++;
            return null;
        }

        $this->hits++;
        $cachedResponse = clone $entry['response'];
        $cachedResponse->setHeader('cf-cache-status', 'HIT');
        $cachedResponse->setHeader('age', (string)max(0, (int)(microtime(true) - ($entry['expiresAt'] - 300))));

        return $cachedResponse;
    }

    /**
     * Delete a specific cache entry.
     */
    public function delete(EdgeRequest|string $request): bool
    {
        $key = $this->resolveKey($request);
        if (isset($this->store[$key])) {
            unset($this->store[$key]);
            return true;
        }
        return false;
    }

    /**
     * Invalidate all cached entries tagged with a surrogate key.
     *
     * @return int Number of purged entries
     */
    public function purgeByTag(string $tag): int
    {
        $purged = 0;
        foreach ($this->store as $key => $entry) {
            if (in_array($tag, $entry['tags'], true)) {
                unset($this->store[$key]);
                $purged++;
            }
        }
        return $purged;
    }

    public function clear(): void
    {
        $this->store = [];
    }

    public function count(): int
    {
        return count($this->store);
    }

    /**
     * @return array{hits: int, misses: int, total_entries: int, hit_ratio: float}
     */
    public function stats(): array
    {
        $total = $this->hits + $this->misses;
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'total_entries' => count($this->store),
            'hit_ratio' => $total > 0 ? round($this->hits / $total, 4) : 0.0,
        ];
    }

    protected function resolveKey(EdgeRequest|string $request): string
    {
        if (is_string($request)) {
            return $request;
        }
        return sprintf('%s:%s', $request->getMethod(), $request->getUrl());
    }
}
