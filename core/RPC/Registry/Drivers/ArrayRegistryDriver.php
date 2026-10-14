<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Registry/Drivers/ArrayRegistryDriver.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Registry\Drivers
| Purpose   : High-speed in-memory service registry driver for local testing and single-node clusters.
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

namespace NetFusion\Core\RPC\Registry\Drivers;

use NetFusion\Core\RPC\Contracts\ServiceRegistryInterface;
use NetFusion\Core\RPC\Registry\ServiceInstance;

class ArrayRegistryDriver implements ServiceRegistryInterface
{
    /**
     * @var array<string, array<string, ServiceInstance>> [serviceName => [instanceId => ServiceInstance]]
     */
    protected array $services = [];

    public function register(ServiceInstance $instance): void
    {
        $this->services[$instance->serviceName][$instance->id] = $instance;
    }

    public function deregister(string $instanceId): void
    {
        foreach ($this->services as $serviceName => $instances) {
            if (isset($instances[$instanceId])) {
                unset($this->services[$serviceName][$instanceId]);
                if (empty($this->services[$serviceName])) {
                    unset($this->services[$serviceName]);
                }
                return;
            }
        }
    }

    public function heartbeat(string $instanceId): bool
    {
        foreach ($this->services as $serviceName => $instances) {
            if (isset($instances[$instanceId])) {
                $this->services[$serviceName][$instanceId]->lastHeartbeat = microtime(true);
                return true;
            }
        }
        return false;
    }

    public function discover(string $serviceName): array
    {
        if (!isset($this->services[$serviceName])) {
            return [];
        }

        $healthy = [];
        foreach ($this->services[$serviceName] as $instance) {
            if ($instance->isHealthy()) {
                $healthy[] = $instance;
            }
        }

        return $healthy;
    }

    public function all(): array
    {
        $result = [];
        foreach ($this->services as $serviceName => $instances) {
            $result[$serviceName] = array_values($instances);
        }
        return $result;
    }

    public function pruneExpired(int $ttlSeconds = 30): int
    {
        $purged = 0;
        foreach ($this->services as $serviceName => $instances) {
            foreach ($instances as $id => $instance) {
                if (!$instance->isHealthy($ttlSeconds)) {
                    unset($this->services[$serviceName][$id]);
                    $purged++;
                }
            }
            if (empty($this->services[$serviceName])) {
                unset($this->services[$serviceName]);
            }
        }
        return $purged;
    }

    public function clear(): void
    {
        $this->services = [];
    }
}
