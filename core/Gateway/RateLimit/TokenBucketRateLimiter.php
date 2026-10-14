<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Gateway\RateLimit;

use NetFusion\Core\Gateway\Contracts\GatewayStoreInterface;
use NetFusion\Core\Gateway\Contracts\RateLimiterInterface;
use NetFusion\Core\Gateway\Models\GatewayRequest;

/**
 * Distributed Token-Bucket rate limiter.
 *
 * Each key gets a bucket with `capacity` tokens that refills at `refillRate`
 * tokens/second. The bucket state is persisted via GatewayStoreInterface for
 * multi-process / distributed scenarios.
 */
class TokenBucketRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly GatewayStoreInterface $store,
        private readonly int $capacity = 60,       // max tokens in bucket
        private readonly float $refillRate = 1.0,  // tokens added per second
        private readonly string $dimension = 'ip'  // ip|api_key|user|tenant
    ) {}

    public function isAllowed(GatewayRequest $request): bool
    {
        $key    = $request->getRateLimitKey($this->dimension);
        $bucket = $this->getRefreshedBucket($key);
        return $bucket['tokens'] > 0;
    }

    public function consume(GatewayRequest $request): bool
    {
        $key    = $request->getRateLimitKey($this->dimension);
        $bucket = $this->getRefreshedBucket($key);

        if ($bucket['tokens'] <= 0) {
            return false;
        }

        $bucket['tokens']--;
        $this->store->setRateBucket($key, $bucket['tokens'], $bucket['reset_at']);
        return true;
    }

    public function getRemainingTokens(string $key): int
    {
        $bucket = $this->getRefreshedBucket($key);
        return max(0, $bucket['tokens']);
    }

    public function getResetTime(string $key): int
    {
        $bucket = $this->getRefreshedBucket($key);
        return $bucket['reset_at'];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Loads the bucket from the store, applies token refill based on elapsed
     * time, and returns the current state.
     *
     * @return array{tokens: int, reset_at: int}
     */
    private function getRefreshedBucket(string $key): array
    {
        $now    = time();
        $stored = $this->store->getRateBucket($key);

        if ($stored === null) {
            // First access — full bucket
            $bucket = ['tokens' => $this->capacity - 1, 'reset_at' => $now + (int)ceil($this->capacity / $this->refillRate)];
            $this->store->setRateBucket($key, $bucket['tokens'], $bucket['reset_at']);
            return $bucket;
        }

        // Refill tokens based on elapsed time since last reset window
        $elapsed       = max(0, $stored['reset_at'] - $now);
        $windowSeconds = (int)ceil($this->capacity / $this->refillRate);
        $elapsed       = $windowSeconds - $elapsed; // seconds since window started

        $refilled = (int)floor($elapsed * $this->refillRate);
        $tokens   = min($this->capacity, $stored['tokens'] + $refilled);

        return ['tokens' => $tokens, 'reset_at' => $stored['reset_at']];
    }
}
