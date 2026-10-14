<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Contracts/CircuitBreakerInterface.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Contracts
| Purpose   : Interface for failure isolation, degradation and fallback protection.
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

namespace NetFusion\Core\RPC\Contracts;

interface CircuitBreakerInterface
{
    /**
     * Execute an action with circuit breaker protection.
     *
     * @param callable      $action
     * @param callable|null $fallback
     * @return mixed
     */
    public function execute(callable $action, ?callable $fallback = null): mixed;

    /**
     * Get the current circuit state: 'CLOSED', 'OPEN', 'HALF_OPEN'.
     */
    public function getState(): string;

    /**
     * Check if the circuit allows execution.
     */
    public function isAvailable(): bool;

    /**
     * Record a successful invocation.
     */
    public function recordSuccess(): void;

    /**
     * Record a failed invocation.
     */
    public function recordFailure(): void;

    /**
     * Reset the circuit breaker to CLOSED state.
     */
    public function reset(): void;
}
