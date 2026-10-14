<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Contracts/ServiceRegistryInterface.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Contracts
| Purpose   : Interface for service registration, node discovery and health tracking.
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

use NetFusion\Core\RPC\Registry\ServiceInstance;

interface ServiceRegistryInterface
{
    /**
     * Register a service instance node.
     */
    public function register(ServiceInstance $instance): void;

    /**
     * Deregister a service instance node by ID.
     */
    public function deregister(string $instanceId): void;

    /**
     * Send a heartbeat for a registered node.
     */
    public function heartbeat(string $instanceId): bool;

    /**
     * Discover all healthy active instances for a given service name.
     *
     * @param string $serviceName
     * @return array<ServiceInstance>
     */
    public function discover(string $serviceName): array;

    /**
     * Get all registered services across the mesh.
     *
     * @return array<string, array<ServiceInstance>>
     */
    public function all(): array;

    /**
     * Clean up expired / unhealthy instances based on heartbeat TTL.
     *
     * @param int $ttlSeconds
     * @return int Count of purged nodes
     */
    public function pruneExpired(int $ttlSeconds = 30): int;
}
