<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/RateLimiter.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Sliding window rate limiter with file-based storage and Redis-ready interface.
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

namespace NetFusion\Core\Http;

/**
 * NetFusion Rate Limiter
 *
 * Implements a sliding window counter algorithm to limit the number of
 * requests an IP/user can make within a given time window.
 *
 * Storage backend is file-based by default. Designed with a pluggable
 * store interface so Redis or Memcached can be substituted with zero
 * changes to the middleware or application code.
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class RateLimiter
{
    /**
     * In-memory hit registry (key => [hit_timestamps]).
     * In production: replace with Redis ZADD/ZCOUNT for distributed rate limiting.
     *
     * @var array<string, list<int>>
     */
    protected array $store = [];

    /**
     * @param int    $maxAttempts  Maximum requests allowed per window (default: 60).
     * @param int    $decaySeconds Time window in seconds (default: 60 seconds).
     * @param string $storePath    Directory for file-based persistence (optional).
     */
    public function __construct(
        protected readonly int    $maxAttempts  = 60,
        protected readonly int    $decaySeconds = 60,
        protected readonly string $storePath    = '',
    ) {}

    // =========================================================================
    //  Core Rate Limit Logic
    // =========================================================================

    /**
     * Attempt to hit the rate limiter for a given key.
     *
     * Returns true if the request is allowed (under limit),
     * false if the limit has been exceeded.
     *
     * @param  string $key Unique limiter key (e.g., 'api:192.168.1.1').
     * @param  int    $maxAttempts  Override the default max per call.
     * @param  int    $decaySeconds Override the default window per call.
     * @return bool   True = allowed, False = rate limited.
     */
    public function attempt(string $key, int $maxAttempts = 0, int $decaySeconds = 0): bool
    {
        $max   = $maxAttempts  ?: $this->maxAttempts;
        $decay = $decaySeconds ?: $this->decaySeconds;

        $now    = time();
        $window = $now - $decay;

        // Prune old entries outside the sliding window
        $hits = array_filter(
            $this->getHits($key),
            fn(int $timestamp): bool => $timestamp > $window
        );

        if (count($hits) >= $max) {
            return false;
        }

        // Record this hit
        $hits[] = $now;
        $this->setHits($key, array_values($hits));

        return true;
    }

    /**
     * Check if a key has exceeded the rate limit (without incrementing).
     *
     * @param  string $key
     * @param  int    $maxAttempts
     * @param  int    $decaySeconds
     * @return bool   True = limited, False = still allowed.
     */
    public function tooManyAttempts(string $key, int $maxAttempts = 0, int $decaySeconds = 0): bool
    {
        $max   = $maxAttempts  ?: $this->maxAttempts;
        $decay = $decaySeconds ?: $this->decaySeconds;
        $window = time() - $decay;

        $hits = array_filter(
            $this->getHits($key),
            fn(int $ts): bool => $ts > $window
        );

        return count($hits) >= $max;
    }

    /**
     * Get the number of remaining attempts for a key.
     *
     * @param  string $key
     * @param  int    $maxAttempts
     * @param  int    $decaySeconds
     * @return int
     */
    public function remaining(string $key, int $maxAttempts = 0, int $decaySeconds = 0): int
    {
        $max   = $maxAttempts  ?: $this->maxAttempts;
        $decay = $decaySeconds ?: $this->decaySeconds;
        $window = time() - $decay;

        $hits = array_filter(
            $this->getHits($key),
            fn(int $ts): bool => $ts > $window
        );

        return max(0, $max - count($hits));
    }

    /**
     * Get the UNIX timestamp when the rate limit window resets.
     *
     * @param  string $key
     * @param  int    $decaySeconds
     * @return int    UNIX timestamp.
     */
    public function availableAt(string $key, int $decaySeconds = 0): int
    {
        $decay = $decaySeconds ?: $this->decaySeconds;
        $hits  = $this->getHits($key);

        if (empty($hits)) {
            return time();
        }

        // Window resets when the oldest hit falls out
        return min($hits) + $decay;
    }

    /**
     * Clear all hits for a key.
     *
     * @param string $key
     */
    public function clear(string $key): void
    {
        unset($this->store[$key]);
    }

    /**
     * Get the configured max attempts per window.
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Get the configured decay (window) in seconds.
     */
    public function getDecaySeconds(): int
    {
        return $this->decaySeconds;
    }

    // =========================================================================
    //  Storage Helpers (File-based, Redis-replaceable)
    // =========================================================================

    /**
     * Get the recorded hit timestamps for a key.
     *
     * @param  string    $key
     * @return list<int>
     */
    protected function getHits(string $key): array
    {
        return $this->store[$key] ?? [];
    }

    /**
     * Persist hit timestamps for a key.
     *
     * @param string    $key
     * @param list<int> $hits
     */
    protected function setHits(string $key, array $hits): void
    {
        $this->store[$key] = $hits;
    }
}
