<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Contracts/RpcClientInterface.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Contracts
| Purpose   : Interface for dispatching synchronous or asynchronous RPC requests.
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

interface RpcClientInterface
{
    /**
     * Dispatch an RPC call to a named service method.
     *
     * @param string               $service
     * @param string               $method
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @return mixed
     */
    public function call(string $service, string $method, array $params = [], array $options = []): mixed;

    /**
     * Get a fluent proxy for a named service.
     *
     * @param string $service
     * @return object
     */
    public function service(string $service): object;
}
