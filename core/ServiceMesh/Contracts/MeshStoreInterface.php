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
use NetFusion\Core\ServiceMesh\Routing\TrafficRoute;
use NetFusion\Core\ServiceMesh\Security\MeshAuthorizationPolicy;

interface MeshStoreInterface
{
    public function saveServiceInstance(ServiceInstance $instance): void;
    public function removeServiceInstance(string $serviceName, string $instanceId): bool;
    /** @return array<ServiceInstance> */
    public function getServiceInstances(string $serviceName): array;
    /** @return array<string, array<ServiceInstance>> */
    public function getAllServices(): array;

    public function saveRoute(TrafficRoute $route): void;
    public function getRoutes(): array;

    public function savePolicy(MeshAuthorizationPolicy $policy): void;
    public function getPolicies(): array;

    public function saveCertificate(string $spiffeId, array $certData): void;
    public function getCertificate(string $spiffeId): ?array;

    public function recordMetric(string $metricName, float $value, array $tags = []): void;
}
