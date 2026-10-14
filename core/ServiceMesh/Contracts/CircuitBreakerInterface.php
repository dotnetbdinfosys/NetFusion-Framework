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

namespace NetFusion\Core\ServiceMesh\Contracts;

interface CircuitBreakerInterface
{
    public const STATE_CLOSED = 'CLOSED';
    public const STATE_OPEN = 'OPEN';
    public const STATE_HALF_OPEN = 'HALF_OPEN';

    /**
     * Check if request is permitted through the circuit.
     */
    public function allowRequest(): bool;

    /**
     * Record a successful request.
     */
    public function recordSuccess(): void;

    /**
     * Record a failed request (5xx or timeout).
     */
    public function recordFailure(): void;

    /**
     * Get current state (CLOSED, OPEN, HALF_OPEN).
     */
    public function getState(): string;

    /**
     * Reset breaker state.
     */
    public function reset(): void;
}
