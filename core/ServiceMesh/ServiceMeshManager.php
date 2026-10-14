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

namespace NetFusion\Core\ServiceMesh;

use NetFusion\Core\ServiceMesh\Contracts\CertificateManagerInterface;
use NetFusion\Core\ServiceMesh\Contracts\MeshStoreInterface;
use NetFusion\Core\ServiceMesh\Contracts\ServiceMeshInterface;
use NetFusion\Core\ServiceMesh\Health\ServiceInstance;
use NetFusion\Core\ServiceMesh\Proxy\MeshRequestContext;
use NetFusion\Core\ServiceMesh\Proxy\MeshResponse;
use NetFusion\Core\ServiceMesh\Proxy\SidecarProxy;
use NetFusion\Core\ServiceMesh\Routing\TrafficRoute;
use NetFusion\Core\ServiceMesh\Security\CertificateManager;
use NetFusion\Core\ServiceMesh\Security\MeshAuthorizationPolicy;
use NetFusion\Core\ServiceMesh\Security\SpiffeId;
use NetFusion\Core\ServiceMesh\Store\ArrayMeshStore;

class ServiceMeshManager implements ServiceMeshInterface
{
    protected string $trustDomain;
    protected MeshStoreInterface $store;
    protected CertificateManagerInterface $certificateManager;
    /** @var array<string, SidecarProxy> */
    protected array $proxies = [];
    /** @var array<string, TrafficRoute> */
    protected array $routes = [];
    /** @var array<string, MeshAuthorizationPolicy> */
    protected array $policies = [];
    protected float $startTime;

    public function __construct(
        string $trustDomain = 'cluster.local',
        ?MeshStoreInterface $store = null,
        ?CertificateManagerInterface $certManager = null
    ) {
        $this->trustDomain = $trustDomain;
        $this->store = $store ?? new ArrayMeshStore();
        $this->certificateManager = $certManager ?? new CertificateManager($trustDomain, $this->store);
        $this->startTime = microtime(true);
    }

    public function proxy(?string $serviceName = null): SidecarProxy
    {
        $name = $serviceName ?? 'gateway';
        if (!isset($this->proxies[$name])) {
            $this->proxies[$name] = new SidecarProxy($name, $this);
        }
        return $this->proxies[$name];
    }

    public function registerService(ServiceInstance $instance): self
    {
        $this->store->saveServiceInstance($instance);
        return $this;
    }

    public function deregisterService(string $serviceName, string $instanceId): bool
    {
        return $this->store->removeServiceInstance($serviceName, $instanceId);
    }

    public function getInstances(string $serviceName): array
    {
        return $this->store->getServiceInstances($serviceName);
    }

    public function getAllServices(): array
    {
        return $this->store->getAllServices();
    }

    public function addRoute(TrafficRoute $route): self
    {
        $this->routes[$route->getName()] = $route;
        $this->store->saveRoute($route);
        return $this;
    }

    public function getRoutes(): array
    {
        return array_values($this->routes);
    }

    public function matchRoute(MeshRequestContext $context): ?TrafficRoute
    {
        foreach ($this->routes as $route) {
            if ($route->matches($context)) {
                return $route;
            }
        }
        return null;
    }

    public function addPolicy(MeshAuthorizationPolicy $policy): self
    {
        $this->policies[$policy->getName()] = $policy;
        $this->store->savePolicy($policy);
        return $this;
    }

    public function getPolicies(): array
    {
        return array_values($this->policies);
    }

    public function getCertificateManager(): CertificateManagerInterface
    {
        return $this->certificateManager;
    }

    public function getStore(): MeshStoreInterface
    {
        return $this->store;
    }

    public function setStore(MeshStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function getTrustDomain(): string
    {
        return $this->trustDomain;
    }

    /**
     * Dispatch an outbound service-to-service call via default sidecar gateway.
     */
    public function call(
        string $targetService,
        string $path,
        string $method = 'GET',
        array $headers = [],
        mixed $body = null
    ): MeshResponse {
        $proxy = $this->proxy('gateway');
        $ctx = new MeshRequestContext($targetService, $path, $method, $headers, $body);
        return $proxy->handleOutbound($ctx);
    }

    public function getStats(): array
    {
        $services = $this->store->getAllServices();
        $totalInstances = 0;
        $healthyInstances = 0;

        foreach ($services as $instances) {
            foreach ($instances as $inst) {
                $totalInstances++;
                if ($inst->isAvailable()) {
                    $healthyInstances++;
                }
            }
        }

        return [
            'uptime_seconds' => round(microtime(true) - $this->startTime, 2),
            'trust_domain' => $this->trustDomain,
            'total_services' => count($services),
            'total_instances' => $totalInstances,
            'healthy_instances' => $healthyInstances,
            'registered_routes' => count($this->routes),
            'registered_policies' => count($this->policies),
            'active_sidecars' => count($this->proxies),
        ];
    }
}
