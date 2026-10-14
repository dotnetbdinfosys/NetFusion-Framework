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

namespace NetFusion\Core\WAF\DDoS;

class AdaptiveRateLimiter
{
    /**
     * @var array<string, array<int>> IP => array of request timestamps
     */
    protected array $requestWindows = [];

    /**
     * @var array<string, int> IP => auto-ban expiration timestamp
     */
    protected array $bannedIps = [];

    /**
     * @param int $maxRequests Maximum requests allowed within window
     * @param int $windowSeconds Sliding window size in seconds
     * @param int $banThresholdRatio Multiplier threshold to trigger auto-ban (e.g. 3x maxRequests)
     * @param int $banDurationSeconds Duration of auto-ban in seconds (default 900 = 15m)
     */
    public function __construct(
        protected int $maxRequests = 100,
        protected int $windowSeconds = 60,
        protected int $banThresholdRatio = 3,
        protected int $banDurationSeconds = 900
    ) {
    }

    /**
     * Record request from IP and check if allowed.
     *
     * @return array{allowed: bool, current_rate: int, is_banned: bool, delay_ms: int}
     */
    public function hit(string $ip): array
    {
        $now = time();

        // 1. Check if currently auto-banned
        if (isset($this->bannedIps[$ip])) {
            if ($this->bannedIps[$ip] > $now) {
                return [
                    'allowed' => false,
                    'current_rate' => $this->maxRequests * $this->banThresholdRatio,
                    'is_banned' => true,
                    'delay_ms' => 0,
                ];
            }
            unset($this->bannedIps[$ip]);
        }

        // 2. Clean up old timestamps outside sliding window
        $windowStart = $now - $this->windowSeconds;
        if (!isset($this->requestWindows[$ip])) {
            $this->requestWindows[$ip] = [];
        }

        $this->requestWindows[$ip] = array_filter(
            $this->requestWindows[$ip],
            fn($ts) => $ts > $windowStart
        );

        $this->requestWindows[$ip][] = $now;
        $currentRate = count($this->requestWindows[$ip]);

        // 3. Check if burst exceeds ban threshold
        if ($currentRate >= ($this->maxRequests * $this->banThresholdRatio)) {
            $this->bannedIps[$ip] = $now + $this->banDurationSeconds;
            return [
                'allowed' => false,
                'current_rate' => $currentRate,
                'is_banned' => true,
                'delay_ms' => 0,
            ];
        }

        // 4. Soft throttle / rate limit
        if ($currentRate > $this->maxRequests) {
            $excess = $currentRate - $this->maxRequests;
            $delayMs = min(500, $excess * 50); // progressive delay
            return [
                'allowed' => false,
                'current_rate' => $currentRate,
                'is_banned' => false,
                'delay_ms' => $delayMs,
            ];
        }

        return [
            'allowed' => true,
            'current_rate' => $currentRate,
            'is_banned' => false,
            'delay_ms' => 0,
        ];
    }

    public function isBanned(string $ip): bool
    {
        if (isset($this->bannedIps[$ip])) {
            if ($this->bannedIps[$ip] > time()) {
                return true;
            }
            unset($this->bannedIps[$ip]);
        }
        return false;
    }

    public function reset(string $ip): void
    {
        unset($this->requestWindows[$ip]);
        unset($this->bannedIps[$ip]);
    }

    public function clear(): void
    {
        $this->requestWindows = [];
        $this->bannedIps = [];
    }
}
