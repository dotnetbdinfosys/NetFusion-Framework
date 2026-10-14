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

use NetFusion\Core\ServiceMesh\Health\ServiceInstance;
use NetFusion\Core\ServiceMesh\Proxy\MeshRequestContext;
use NetFusion\Core\ServiceMesh\Proxy\MeshResponse;
use NetFusion\Core\ServiceMesh\Proxy\SidecarProxy;
use NetFusion\Core\ServiceMesh\Routing\TrafficRoute;
use NetFusion\Core\ServiceMesh\Security\MeshAuthorizationPolicy;

interface ServiceMeshInterface
{
    /**
     * Get or create a sidecar proxy for a local service.
     */
    public function proxy(?string $serviceName = null): SidecarProxy;

    /**
     * Register a service instance in the mesh registry.
     */
    public function registerService(ServiceInstance $instance): self;

    /**
     * Deregister a service instance.
     */
    public function deregisterService(string $serviceName, string $instanceId): bool;

    /**
     * Get all healthy instances for a service.
     *
     * @return array<ServiceInstance>
     */
    public function getInstances(string $serviceName): array;

    /**
     * Register a traffic route definition.
     */
    public function addRoute(TrafficRoute $route): self;

    /**
     * Register an authorization policy.
     */
    public function addPolicy(MeshAuthorizationPolicy $policy): self;

    /**
     * Forward an outbound service-to-service call through the mesh.
     */
    public function call(string $targetService, string $path, string $method = 'GET', array $headers = [], mixed $body = null): MeshResponse;

    /**
     * Get mesh health, topology and latency statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array;
}
