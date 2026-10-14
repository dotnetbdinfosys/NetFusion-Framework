<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Client/RpcProxy.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Client
| Purpose   : Fluent magic proxy for invoking remote microservice methods with clean syntax.
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

namespace NetFusion\Core\RPC\Client;

use NetFusion\Core\RPC\Contracts\RpcClientInterface;

class RpcProxy
{
    public function __construct(
        protected RpcClientInterface $client,
        protected string $serviceName,
        protected array $options = []
    ) {}

    public function withOptions(array $options): self
    {
        return new self($this->client, $this->serviceName, array_merge($this->options, $options));
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->client->call($this->serviceName, $method, $arguments, $this->options);
    }
}
