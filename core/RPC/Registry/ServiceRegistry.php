<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Registry/ServiceRegistry.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Registry
| Purpose   : Service Discovery Coordinator managing drivers and health checks.
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

namespace NetFusion\Core\RPC\Registry;

use NetFusion\Core\RPC\Contracts\ServiceRegistryInterface;
use NetFusion\Core\RPC\Registry\Drivers\ArrayRegistryDriver;

class ServiceRegistry implements ServiceRegistryInterface
{
    /**
     * @var array<string, ServiceRegistryInterface>
     */
    protected array $drivers = [];
    protected string $defaultDriver = 'array';

    public function __construct(?ServiceRegistryInterface $defaultDriver = null)
    {
        if ($defaultDriver !== null) {
            $this->drivers['default'] = $defaultDriver;
            $this->defaultDriver = 'default';
        } else {
            $this->drivers['array'] = new ArrayRegistryDriver();
            $this->defaultDriver = 'array';
        }
    }

    public function addDriver(string $name, ServiceRegistryInterface $driver): self
    {
        $this->drivers[$name] = $driver;
        return $this;
    }

    public function setDefaultDriver(string $name): self
    {
        $this->defaultDriver = $name;
        return $this;
    }

    public function driver(?string $name = null): ServiceRegistryInterface
    {
        $name = $name ?? $this->defaultDriver;
        return $this->drivers[$name] ?? $this->drivers[$this->defaultDriver] ?? new ArrayRegistryDriver();
    }

    public function register(ServiceInstance $instance): void
    {
        $this->driver()->register($instance);
    }

    public function deregister(string $instanceId): void
    {
        $this->driver()->deregister($instanceId);
    }

    public function heartbeat(string $instanceId): bool
    {
        return $this->driver()->heartbeat($instanceId);
    }

    public function discover(string $serviceName): array
    {
        return $this->driver()->discover($serviceName);
    }

    public function all(): array
    {
        return $this->driver()->all();
    }

    public function pruneExpired(int $ttlSeconds = 30): int
    {
        return $this->driver()->pruneExpired($ttlSeconds);
    }
}
