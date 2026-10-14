<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Contracts/RpcServerInterface.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Contracts
| Purpose   : Interface for microservice RPC server daemons.
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

interface RpcServerInterface
{
    /**
     * Register a service handler under a unique service name.
     *
     * @param string        $serviceName
     * @param object|string $handler (Instance or class string)
     */
    public function register(string $serviceName, object|string $handler): self;

    /**
     * Handle a single incoming raw RPC request string and return the response string.
     *
     * @param string $rawPayload
     * @return string
     */
    public function handle(string $rawPayload): string;

    /**
     * Start the listening daemon loop on given host & port.
     *
     * @param string $host
     * @param int    $port
     */
    public function serve(string $host = '0.0.0.0', int $port = 9500): void;

    /**
     * Stop the server daemon.
     */
    public function stop(): void;
}
