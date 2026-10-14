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

namespace NetFusion\Core\Gateway\Health;

use NetFusion\Core\Gateway\Contracts\UpstreamInterface;

/**
 * Half-open circuit breaker per upstream.
 *
 * States:
 *   CLOSED   → Normal operation; failures are counted.
 *   OPEN     → All requests fail-fast; re-check after cooldown.
 *   HALF_OPEN → One probe request allowed; success → CLOSED, failure → OPEN.
 */
class CircuitBreaker
{
    private const STATE_CLOSED    = 'closed';
    private const STATE_OPEN      = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    /** @var array<string, array{state:string, failures:int, opened_at:float}> */
    private array $circuits = [];

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 30,
        private readonly int $successThreshold = 2
    ) {}

    /**
     * Returns true if a request is allowed through the circuit.
     */
    public function isAllowed(UpstreamInterface $upstream): bool
    {
        $key = $upstream->getId();
        $this->ensureCircuit($key);

        $state = $this->circuits[$key]['state'];

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            $elapsed = microtime(true) - $this->circuits[$key]['opened_at'];
            if ($elapsed >= $this->cooldownSeconds) {
                $this->circuits[$key]['state']    = self::STATE_HALF_OPEN;
                $this->circuits[$key]['failures'] = 0;
                return true;
            }
            return false;
        }

        // HALF_OPEN: allow one probe
        return true;
    }

    /**
     * Records a successful request.
     */
    public function recordSuccess(UpstreamInterface $upstream): void
    {
        $key = $upstream->getId();
        $this->ensureCircuit($key);

        if ($this->circuits[$key]['state'] === self::STATE_HALF_OPEN) {
            $this->circuits[$key]['failures'] = 0;
            $this->circuits[$key]['state']    = self::STATE_CLOSED;
        } elseif ($this->circuits[$key]['state'] === self::STATE_CLOSED) {
            $this->circuits[$key]['failures'] = max(0, $this->circuits[$key]['failures'] - 1);
        }
    }

    /**
     * Records a failed request and potentially trips the circuit.
     */
    public function recordFailure(UpstreamInterface $upstream): void
    {
        $key = $upstream->getId();
        $this->ensureCircuit($key);

        $this->circuits[$key]['failures']++;

        if (
            $this->circuits[$key]['state'] !== self::STATE_OPEN
            && $this->circuits[$key]['failures'] >= $this->failureThreshold
        ) {
            $this->circuits[$key]['state']     = self::STATE_OPEN;
            $this->circuits[$key]['opened_at'] = microtime(true);
        }
    }

    /**
     * Returns the current state of an upstream's circuit.
     */
    public function getState(UpstreamInterface $upstream): string
    {
        $key = $upstream->getId();
        $this->ensureCircuit($key);
        return $this->circuits[$key]['state'];
    }

    public function getFailures(UpstreamInterface $upstream): int
    {
        $key = $upstream->getId();
        $this->ensureCircuit($key);
        return $this->circuits[$key]['failures'];
    }

    /**
     * Manually resets the circuit for an upstream (admin action).
     */
    public function reset(UpstreamInterface $upstream): void
    {
        $key = $upstream->getId();
        $this->circuits[$key] = [
            'state'     => self::STATE_CLOSED,
            'failures'  => 0,
            'opened_at' => 0.0,
        ];
    }

    private function ensureCircuit(string $key): void
    {
        if (!isset($this->circuits[$key])) {
            $this->circuits[$key] = [
                'state'     => self::STATE_CLOSED,
                'failures'  => 0,
                'opened_at' => 0.0,
            ];
        }
    }
}
