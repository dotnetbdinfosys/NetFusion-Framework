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

namespace NetFusion\Core\ServiceMesh\Store;

use NetFusion\Core\ServiceMesh\Contracts\MeshStoreInterface;
use NetFusion\Core\ServiceMesh\Health\ServiceInstance;
use NetFusion\Core\ServiceMesh\Routing\TrafficRoute;
use NetFusion\Core\ServiceMesh\Security\MeshAuthorizationPolicy;

class ArrayMeshStore implements MeshStoreInterface
{
    /** @var array<string, array<string, ServiceInstance>> [serviceName => [instanceId => ServiceInstance]] */
    protected array $services = [];
    /** @var array<string, TrafficRoute> */
    protected array $routes = [];
    /** @var array<string, MeshAuthorizationPolicy> */
    protected array $policies = [];
    /** @var array<string, array<string, mixed>> */
    protected array $certificates = [];
    /** @var array<array{metric: string, value: float, tags: array<string, mixed>, timestamp: float}> */
    protected array $metrics = [];

    public function saveServiceInstance(ServiceInstance $instance): void
    {
        $service = $instance->getServiceName();
        if (!isset($this->services[$service])) {
            $this->services[$service] = [];
        }
        $this->services[$service][$instance->getId()] = $instance;
    }

    public function removeServiceInstance(string $serviceName, string $instanceId): bool
    {
        if (isset($this->services[$serviceName][$instanceId])) {
            unset($this->services[$serviceName][$instanceId]);
            return true;
        }
        return false;
    }

    public function getServiceInstances(string $serviceName): array
    {
        return array_values($this->services[$serviceName] ?? []);
    }

    public function getAllServices(): array
    {
        $out = [];
        foreach ($this->services as $name => $instances) {
            $out[$name] = array_values($instances);
        }
        return $out;
    }

    public function saveRoute(TrafficRoute $route): void
    {
        $this->routes[$route->getName()] = $route;
    }

    public function getRoutes(): array
    {
        return array_values($this->routes);
    }

    public function savePolicy(MeshAuthorizationPolicy $policy): void
    {
        $this->policies[$policy->getName()] = $policy;
    }

    public function getPolicies(): array
    {
        return array_values($this->policies);
    }

    public function saveCertificate(string $spiffeId, array $certData): void
    {
        $this->certificates[$spiffeId] = $certData;
    }

    public function getCertificate(string $spiffeId): ?array
    {
        return $this->certificates[$spiffeId] ?? null;
    }

    public function recordMetric(string $metricName, float $value, array $tags = []): void
    {
        $this->metrics[] = [
            'metric' => $metricName,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true),
        ];
    }

    public function clear(): void
    {
        $this->services = [];
        $this->routes = [];
        $this->policies = [];
        $this->certificates = [];
        $this->metrics = [];
    }
}
