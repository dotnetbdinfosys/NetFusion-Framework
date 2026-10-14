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

namespace NetFusion\Core\ServiceMesh\Resilience;

use JsonSerializable;
use NetFusion\Core\ServiceMesh\Contracts\CircuitBreakerInterface;

class MeshCircuitBreaker implements CircuitBreakerInterface, JsonSerializable
{
    protected string $name;
    protected int $failureThreshold;
    protected float $recoveryTimeoutSeconds;
    protected int $halfOpenSuccessThreshold;

    protected string $state = self::STATE_CLOSED;
    protected int $failureCount = 0;
    protected int $successCount = 0;
    protected ?float $openedAt = null;

    public function __construct(
        string $name,
        int $failureThreshold = 5,
        float $recoveryTimeoutSeconds = 30.0,
        int $halfOpenSuccessThreshold = 3
    ) {
        $this->name = $name;
        $this->failureThreshold = max(1, $failureThreshold);
        $this->recoveryTimeoutSeconds = max(0.1, $recoveryTimeoutSeconds);
        $this->halfOpenSuccessThreshold = max(1, $halfOpenSuccessThreshold);
    }

    public function allowRequest(): bool
    {
        if ($this->state === self::STATE_CLOSED) {
            return true;
        }

        if ($this->state === self::STATE_OPEN) {
            if ($this->openedAt !== null && (microtime(true) - $this->openedAt) >= $this->recoveryTimeoutSeconds) {
                // Transition to Half-Open to test canary recovery
                $this->state = self::STATE_HALF_OPEN;
                $this->successCount = 0;
                return true;
            }
            return false;
        }

        if ($this->state === self::STATE_HALF_OPEN) {
            return true;
        }

        return true;
    }

    public function recordSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->successCount++;
            if ($this->successCount >= $this->halfOpenSuccessThreshold) {
                // Recovered! Reset to CLOSED
                $this->reset();
            }
        } elseif ($this->state === self::STATE_CLOSED) {
            $this->failureCount = 0;
        }
    }

    public function recordFailure(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            // Immediate trip back to OPEN
            $this->trip();
        } elseif ($this->state === self::STATE_CLOSED) {
            $this->failureCount++;
            if ($this->failureCount >= $this->failureThreshold) {
                $this->trip();
            }
        }
    }

    protected function trip(): void
    {
        $this->state = self::STATE_OPEN;
        $this->openedAt = microtime(true);
        $this->failureCount = 0;
        $this->successCount = 0;
    }

    public function getState(): string
    {
        // Check if OPEN has elapsed recovery timeout
        if ($this->state === self::STATE_OPEN && $this->openedAt !== null) {
            if ((microtime(true) - $this->openedAt) >= $this->recoveryTimeoutSeconds) {
                $this->state = self::STATE_HALF_OPEN;
                $this->successCount = 0;
            }
        }

        return $this->state;
    }

    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->openedAt = null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->getState(),
            'failure_threshold' => $this->failureThreshold,
            'recovery_timeout_seconds' => $this->recoveryTimeoutSeconds,
            'half_open_success_threshold' => $this->halfOpenSuccessThreshold,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
