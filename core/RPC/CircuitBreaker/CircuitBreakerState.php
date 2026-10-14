<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/CircuitBreaker/CircuitBreakerState.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\CircuitBreaker
| Purpose   : State constants for Circuit Breaker pattern.
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

final class CircuitBreakerState
{
    /**
     * Normal state: requests pass through freely.
     */
    public const CLOSED = 'CLOSED';

    /**
     * Tripped state: requests fail fast without calling remote service.
     */
    public const OPEN = 'OPEN';

    /**
     * Trial state: limited trial requests allowed to test service recovery.
     */
    public const HALF_OPEN = 'HALF_OPEN';
}
