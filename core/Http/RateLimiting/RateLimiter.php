<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/RateLimiting/RateLimiter.php
| Module    : Http\RateLimiting
| Namespace : NetFusion\Core\Http\RateLimiting
| Purpose   : High-performance Rate Limiter supporting Token Bucket & fixed window limits.
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

namespace NetFusion\Core\Http\RateLimiting;

use Closure;
use NetFusion\Core\Cache\Contracts\CacheInterface;

/**
 * Class RateLimiter
 *
 * Central rate limiting manager for throttling requests, supporting Token Bucket
 * and window-based rate limits backed by cache storage.
 *
 * @package NetFusion\Core\Http\RateLimiting
 */
class RateLimiter
{
    /**
     * Registered named limiters.
     *
     * @var array<string, Closure>
     */
    protected array $limiters = [];

    /**
     * In-memory array fallback store if cache is absent.
     *
     * @var array<string, mixed>
     */
    protected array $memoryStore = [];

    /**
     * @param CacheInterface|null $cache Cache storage repository.
     */
    public function __construct(protected ?CacheInterface $cache = null)
    {
    }

    /**
     * Register a named rate limiter callback.
     *
     * @param string  $name
     * @param Closure $callback
     * @return $this
     */
    public function for(string $name, Closure $callback): self
    {
        $this->limiters[$name] = $callback;
        return $this;
    }

    /**
     * Register or retrieve a named rate limiter callback.
     *
     * @param string        $name
     * @param Closure|null  $callback
     * @return Closure|$this|null
     */
    public function limiter(string $name, ?Closure $callback = null): mixed
    {
        if ($callback !== null) {
            $this->limiters[$name] = $callback;
            return $this;
        }

        return $this->limiters[$name] ?? null;
    }

    /**
     * Determine if the given key has exceeded the maximum attempts.
     *
     * @param string $key
     * @param int    $maxAttempts
     * @return bool
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        if ($this->attempts($key) >= $maxAttempts) {
            if ($this->has($this->timerKey($key))) {
                return true;
            }

            $this->resetAttempts($key);
        }

        return false;
    }

    /**
     * Increment the counter for a given key for a decay time window.
     *
     * @param string $key
     * @param int    $decaySeconds
     * @return int New count
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $timerKey = $this->timerKey($key);

        if (! $this->has($timerKey)) {
            $this->set($timerKey, time() + $decaySeconds, $decaySeconds);
            $this->set($key, 0, $decaySeconds);
        }

        $hits = (int) $this->get($key, 0) + 1;
        $this->set($key, $hits, $decaySeconds);

        return $hits;
    }

    /**
     * Get the number of attempts for the given key.
     *
     * @param string $key
     * @return int
     */
    public function attempts(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    /**
     * Reset the number of attempts for the given key.
     *
     * @param string $key
     * @return bool
     */
    public function resetAttempts(string $key): bool
    {
        $this->forget($this->timerKey($key));
        return $this->forget($key);
    }

    /**
     * Clear rate limit hits for a key (alias of resetAttempts).
     *
     * @param string $key
     * @return bool
     */
    public function clear(string $key): bool
    {
        return $this->resetAttempts($key);
    }

    /**
     * Get the number of remaining attempts for the given key.
     *
     * @param string $key
     * @param int    $maxAttempts
     * @return int
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Get the number of seconds until the key becomes available.
     *
     * @param string $key
     * @return int
     */
    public function availableIn(string $key): int
    {
        $expiresAt = (int) $this->get($this->timerKey($key), 0);
        return max(0, $expiresAt - time());
    }

    /**
     * Attempt an action if the limit has not been exceeded.
     *
     * @param string   $key
     * @param int      $maxAttempts
     * @param callable $callback
     * @param int      $decaySeconds
     * @return mixed
     */
    public function attempt(string $key, int $maxAttempts, callable $callback, int $decaySeconds = 60): mixed
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->hit($key, $decaySeconds);

        return $callback();
    }

    // ------------------------------------------------------------------------
    // Token Bucket Specific Rate Limiting
    // ------------------------------------------------------------------------

    /**
     * Retrieve or initialize a TokenBucket for a specific key.
     *
     * @param string $key
     * @param int    $capacity
     * @param float  $refillRate
     * @return TokenBucket
     */
    public function bucket(string $key, int $capacity, float $refillRate): TokenBucket
    {
        $bucketKey = 'bucket:' . $key;
        $state = $this->get($bucketKey);

        if (is_array($state)) {
            $bucket = TokenBucket::fromState($state);
        } else {
            $bucket = new TokenBucket($capacity, $refillRate);
        }

        return $bucket;
    }

    /**
     * Attempt to consume tokens from a token bucket identified by key.
     *
     * @param string $key
     * @param int    $capacity
     * @param float  $refillRate
     * @param int    $amount
     * @param int    $ttl
     * @return bool
     */
    public function consumeToken(
        string $key,
        int $capacity,
        float $refillRate,
        int $amount = 1,
        int $ttl = 3600
    ): bool {
        $bucket = $this->bucket($key, $capacity, $refillRate);
        $consumed = $bucket->consume($amount);

        $this->set('bucket:' . $key, $bucket->getState(), $ttl);

        return $consumed;
    }

    /**
     * Get remaining available tokens for the given bucket key.
     *
     * @param string $key
     * @param int    $capacity
     * @param float  $refillRate
     * @return int
     */
    public function tokenBucketRemaining(string $key, int $capacity, float $refillRate): int
    {
        $bucket = $this->bucket($key, $capacity, $refillRate);
        return $bucket->getTokens();
    }

    /**
     * Get seconds until the given bucket key has enough tokens available.
     *
     * @param string $key
     * @param int    $capacity
     * @param float  $refillRate
     * @param int    $amount
     * @return int
     */
    public function tokenBucketAvailableIn(string $key, int $capacity, float $refillRate, int $amount = 1): int
    {
        $bucket = $this->bucket($key, $capacity, $refillRate);
        return $bucket->timeUntilAvailable($amount);
    }

    /**
     * Set the underlying cache repository.
     */
    public function setCache(CacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    // ------------------------------------------------------------------------
    // Cache Access Helpers
    // ------------------------------------------------------------------------

    protected function timerKey(string $key): string
    {
        return $key . ':timer';
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        if ($this->cache !== null) {
            return $this->cache->get($key, $default);
        }

        if (isset($this->memoryStore[$key])) {
            $item = $this->memoryStore[$key];
            if ($item['expires_at'] === null || $item['expires_at'] >= microtime(true)) {
                return $item['value'];
            }
            unset($this->memoryStore[$key]);
        }

        return $default;
    }

    protected function set(string $key, mixed $value, int $ttl = 60): bool
    {
        if ($this->cache !== null) {
            return $this->cache->set($key, $value, $ttl);
        }

        $this->memoryStore[$key] = [
            'value'      => $value,
            'expires_at' => microtime(true) + $ttl,
        ];

        return true;
    }

    protected function has(string $key): bool
    {
        if ($this->cache !== null) {
            return $this->cache->has($key);
        }

        if (isset($this->memoryStore[$key])) {
            $item = $this->memoryStore[$key];
            if ($item['expires_at'] === null || $item['expires_at'] >= microtime(true)) {
                return true;
            }
            unset($this->memoryStore[$key]);
        }

        return false;
    }

    protected function forget(string $key): bool
    {
        if ($this->cache !== null) {
            return $this->cache->delete($key);
        }

        unset($this->memoryStore[$key]);
        return true;
    }
}
