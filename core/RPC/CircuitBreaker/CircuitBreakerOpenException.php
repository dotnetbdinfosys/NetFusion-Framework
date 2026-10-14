<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/CircuitBreaker/CircuitBreakerOpenException.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\CircuitBreaker
| Purpose   : Exception thrown when remote invocation is blocked by open circuit.
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

use NetFusion\Core\RPC\RpcException;

class CircuitBreakerOpenException extends RpcException
{
    public function __construct(string $serviceName, float $resetTimeoutSeconds = 30.0)
    {
        parent::__construct(
            "Circuit Breaker for service [{$serviceName}] is OPEN. Requests are blocked (reset timeout: {$resetTimeoutSeconds}s).",
            self::CIRCUIT_OPEN
        );
    }
}
