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

namespace NetFusion\Core\Payment\Routing;

class GatewayHealthTracker
{
    /**
     * @var array<string, array{success_count: int, failure_count: int, consecutive_failures: int, is_tripped: bool, last_failure_time: int}>
     */
    protected array $stats = [];

    /**
     * @param int $failureThreshold Number of consecutive errors before tripping circuit breaker
     * @param int $cooloffSeconds Seconds before retrying tripped gateway
     */
    public function __construct(
        protected int $failureThreshold = 3,
        protected int $cooloffSeconds = 60
    ) {
    }

    public function recordSuccess(string $gateway): void
    {
        $this->ensureInit($gateway);
        $this->stats[$gateway]['success_count']++;
        $this->stats[$gateway]['consecutive_failures'] = 0;
        $this->stats[$gateway]['is_tripped'] = false;
    }

    public function recordFailure(string $gateway): void
    {
        $this->ensureInit($gateway);
        $this->stats[$gateway]['failure_count']++;
        $this->stats[$gateway]['consecutive_failures']++;
        $this->stats[$gateway]['last_failure_time'] = time();

        if ($this->stats[$gateway]['consecutive_failures'] >= $this->failureThreshold) {
            $this->stats[$gateway]['is_tripped'] = true;
        }
    }

    public function isHealthy(string $gateway): bool
    {
        if (!isset($this->stats[$gateway])) {
            return true;
        }

        $s = $this->stats[$gateway];
        if ($s['is_tripped']) {
            if (time() - $s['last_failure_time'] >= $this->cooloffSeconds) {
                // Cooloff elapsed: permit half-open retry
                return true;
            }
            return false;
        }

        return true;
    }

    public function getSuccessRate(string $gateway): float
    {
        if (!isset($this->stats[$gateway])) {
            return 100.0;
        }

        $total = $this->stats[$gateway]['success_count'] + $this->stats[$gateway]['failure_count'];
        if ($total === 0) {
            return 100.0;
        }

        return round(($this->stats[$gateway]['success_count'] / $total) * 100, 2);
    }

    public function reset(?string $gateway = null): void
    {
        if ($gateway !== null) {
            unset($this->stats[$gateway]);
        } else {
            $this->stats = [];
        }
    }

    public function getStatus(string $gateway): array
    {
        $this->ensureInit($gateway);
        return array_merge($this->stats[$gateway], [
            'is_healthy' => $this->isHealthy($gateway),
            'success_rate' => $this->getSuccessRate($gateway),
        ]);
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    protected function ensureInit(string $gateway): void
    {
        if (!isset($this->stats[$gateway])) {
            $this->stats[$gateway] = [
                'success_count' => 0,
                'failure_count' => 0,
                'consecutive_failures' => 0,
                'is_tripped' => false,
                'last_failure_time' => 0,
            ];
        }
    }
}
