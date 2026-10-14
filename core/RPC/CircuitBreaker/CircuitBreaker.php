<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/CircuitBreaker/CircuitBreaker.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\CircuitBreaker
| Purpose   : Production-grade Circuit Breaker protecting microservices against cascading failures.
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

namespace NetFusion\Core\RPC\CircuitBreaker;

use NetFusion\Core\RPC\Contracts\CircuitBreakerInterface;
use Throwable;

class CircuitBreaker implements CircuitBreakerInterface
{
    protected string $state = CircuitBreakerState::CLOSED;
    protected int $failureCount = 0;
    protected int $successCount = 0;
    protected int $consecutiveSuccessCount = 0;
    protected ?float $lastFailureTime = null;
    protected ?float $lastStateChangeTime = null;

    /**
     * @param string $serviceName Name of protected service or operation
     * @param int    $failureThreshold Number of failures before tripping to OPEN (default 5)
     * @param float  $resetTimeoutSeconds Time to wait in OPEN before trying HALF_OPEN (default 30.0s)
     * @param int    $halfOpenSuccessThreshold Successful trials required in HALF_OPEN to close (default 2)
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly int $failureThreshold = 5,
        public readonly float $resetTimeoutSeconds = 30.0,
        public readonly int $halfOpenSuccessThreshold = 2
    ) {
        $this->lastStateChangeTime = microtime(true);
    }

    /**
     * Execute protected action with fallback support.
     */
    public function execute(callable $action, ?callable $fallback = null): mixed
    {
        if (!$this->isAvailable()) {
            if ($fallback !== null) {
                return $fallback(new CircuitBreakerOpenException($this->serviceName, $this->resetTimeoutSeconds));
            }
            throw new CircuitBreakerOpenException($this->serviceName, $this->resetTimeoutSeconds);
        }

        try {
            $result = $action();
            $this->recordSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->recordFailure();

            if ($fallback !== null) {
                return $fallback($e);
            }

            throw $e;
        }
    }

    /**
     * Get current state (evaluates timeout transitions automatically).
     */
    public function getState(): string
    {
        $this->evaluateState();
        return $this->state;
    }

    /**
     * Check if invocation is permitted.
     */
    public function isAvailable(): bool
    {
        $this->evaluateState();
        return $this->state !== CircuitBreakerState::OPEN;
    }

    /**
     * Record a successful invocation.
     */
    public function recordSuccess(): void
    {
        $this->evaluateState();
        $this->successCount++;

        if ($this->state === CircuitBreakerState::HALF_OPEN) {
            $this->consecutiveSuccessCount++;
            if ($this->consecutiveSuccessCount >= $this->halfOpenSuccessThreshold) {
                $this->transitionTo(CircuitBreakerState::CLOSED);
            }
        } elseif ($this->state === CircuitBreakerState::CLOSED) {
            // Gradually decay failures on success
            $this->failureCount = max(0, $this->failureCount - 1);
        }
    }

    /**
     * Record a failure.
     */
    public function recordFailure(): void
    {
        $this->evaluateState();
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);

        if ($this->state === CircuitBreakerState::HALF_OPEN) {
            // Any failure in half-open state immediately trips circuit back to OPEN
            $this->transitionTo(CircuitBreakerState::OPEN);
        } elseif ($this->state === CircuitBreakerState::CLOSED) {
            if ($this->failureCount >= $this->failureThreshold) {
                $this->transitionTo(CircuitBreakerState::OPEN);
            }
        }
    }

    /**
     * Manually trip or reset circuit breaker state.
     */
    public function forceState(string $state): void
    {
        $this->transitionTo($state);
    }

    /**
     * Reset breaker to default CLOSED state.
     */
    public function reset(): void
    {
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->consecutiveSuccessCount = 0;
        $this->lastFailureTime = null;
        $this->transitionTo(CircuitBreakerState::CLOSED);
    }

    /**
     * Get metrics telemetry snapshot.
     */
    public function getMetrics(): array
    {
        return [
            'service'                   => $this->serviceName,
            'state'                     => $this->getState(),
            'failure_count'             => $this->failureCount,
            'success_count'             => $this->successCount,
            'failure_threshold'         => $this->failureThreshold,
            'reset_timeout_seconds'     => $this->resetTimeoutSeconds,
            'last_failure_time'         => $this->lastFailureTime,
            'last_state_change_time'    => $this->lastStateChangeTime,
        ];
    }

    /**
     * Internal state evaluator for timeout transitions.
     */
    protected function evaluateState(): void
    {
        if ($this->state === CircuitBreakerState::OPEN) {
            $now = microtime(true);
            if ($this->lastFailureTime !== null && ($now - $this->lastFailureTime) >= $this->resetTimeoutSeconds) {
                $this->transitionTo(CircuitBreakerState::HALF_OPEN);
            }
        }
    }

    /**
     * Transition circuit to new state.
     */
    protected function transitionTo(string $newState): void
    {
        $this->state = $newState;
        $this->lastStateChangeTime = microtime(true);

        if ($newState === CircuitBreakerState::CLOSED) {
            $this->failureCount = 0;
            $this->consecutiveSuccessCount = 0;
        } elseif ($newState === CircuitBreakerState::HALF_OPEN) {
            $this->consecutiveSuccessCount = 0;
        }
    }
}
