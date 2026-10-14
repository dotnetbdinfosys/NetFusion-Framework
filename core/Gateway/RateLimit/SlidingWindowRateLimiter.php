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
 * Sliding-Window rate limiter.
 *
 * Combines two fixed windows (current and previous) weighted by the position
 * within the current window to approximate a true sliding window.
 * Accurate within ≈ 1% deviation without per-request storage overhead.
 */
class SlidingWindowRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly GatewayStoreInterface $store,
        private readonly int $limit = 100,         // max requests per window
        private readonly int $windowSeconds = 60,  // window size in seconds
        private readonly string $dimension = 'ip'
    ) {}

    public function isAllowed(GatewayRequest $request): bool
    {
        $key = $request->getRateLimitKey($this->dimension) . ':sw';
        return $this->estimateCount($key) < $this->limit;
    }

    public function consume(GatewayRequest $request): bool
    {
        $key   = $request->getRateLimitKey($this->dimension) . ':sw';
        $count = $this->estimateCount($key);

        if ($count >= $this->limit) {
            return false;
        }

        // Store current window count + 1
        $now      = time();
        $windowId = (int)floor($now / $this->windowSeconds);
        $resetAt  = ($windowId + 1) * $this->windowSeconds;

        $current = $this->store->getRateBucket($key . ':' . $windowId);
        $tokens  = ($current['tokens'] ?? 0) + 1;
        $this->store->setRateBucket($key . ':' . $windowId, $tokens, $resetAt + $this->windowSeconds);

        return true;
    }

    public function getRemainingTokens(string $key): int
    {
        $remaining = $this->limit - (int)ceil($this->estimateCount($key . ':sw'));
        return max(0, $remaining);
    }

    public function getResetTime(string $key): int
    {
        $now      = time();
        $windowId = (int)floor($now / $this->windowSeconds);
        return ($windowId + 1) * $this->windowSeconds;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function estimateCount(string $key): float
    {
        $now      = time();
        $windowId = (int)floor($now / $this->windowSeconds);
        $elapsed  = $now % $this->windowSeconds; // seconds into current window

        // Current window count
        $current = $this->store->getRateBucket($key . ':' . $windowId);
        $currentCount = $current['tokens'] ?? 0;

        // Previous window count
        $previous = $this->store->getRateBucket($key . ':' . ($windowId - 1));
        $previousCount = $previous['tokens'] ?? 0;

        // Weighted estimate: prev × (window - elapsed) / window + current
        $weight = ($this->windowSeconds - $elapsed) / $this->windowSeconds;
        return ($previousCount * $weight) + $currentCount;
    }
}
