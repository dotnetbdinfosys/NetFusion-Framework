<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Network;

use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Models\Service;
use RuntimeException;

/**
 * Kubernetes Service ClusterIP Virtual Proxy (kube-proxy simulation).
 * Routes traffic addressed to Service ClusterIPs to healthy ready Pod endpoints.
 */
class ServiceClusterProxy
{
    /**
     * @var array<string, Service> Map of ClusterIP => Service
     */
    protected array $serviceByIp = [];

    /**
     * @var array<string, int> Round-robin counter per service name
     */
    protected array $rrCounters = [];

    protected int $nextServiceHostId = 1;

    /**
     * @param string $serviceSubnetPrefix (default: "10.96.0")
     */
    public function __construct(
        protected string $serviceSubnetPrefix = '10.96.0'
    ) {
    }

    /**
     * Register a Service and assign it a virtual ClusterIP if not already set.
     */
    public function registerService(Service $service): string
    {
        if ($service->getClusterIp() === null) {
            $ip = "{$this->serviceSubnetPrefix}.{$this->nextServiceHostId}";
            $this->nextServiceHostId++;
            $service->setClusterIp($ip);
        }

        $this->serviceByIp[$service->getClusterIp()] = $service;
        return $service->getClusterIp();
    }

    /**
     * Sync Service Endpoints from all cluster pods matching the selector and ready.
     *
     * @param Pod[] $clusterPods
     */
    public function syncEndpoints(Service $service, array $clusterPods): void
    {
        $endpoints = [];

        foreach ($clusterPods as $pod) {
            if ($service->matchesPod($pod) && $pod->isReady() && $pod->getIp() !== null) {
                $endpoints[] = $pod->getIp();
            }
        }

        $service->setEndpoints($endpoints);
    }

    /**
     * Route a request targeting a Service ClusterIP and port to an optimal backend Pod endpoint.
     *
     * @return array{target_ip: string, target_port: int, service_name: string}
     * @throws RuntimeException If no service found or no ready endpoints available
     */
    public function route(string $clusterIp, int $port): array
    {
        $service = $this->serviceByIp[$clusterIp] ?? null;
        if ($service === null) {
            throw new RuntimeException("Connection refused: No Kubernetes Service found at ClusterIP '{$clusterIp}'");
        }

        $endpoints = $service->getEndpoints();
        if (empty($endpoints)) {
            throw new RuntimeException("Service Unavailable (503): No ready endpoints available for Service '{$service->getName()}'");
        }

        // Find targetPort from service port definitions
        $targetPort = $port;
        foreach ($service->getPorts() as $p) {
            if ($p['port'] === $port) {
                $targetPort = $p['targetPort'];
                break;
            }
        }

        // Round-robin selection
        $name = $service->getName();
        $this->rrCounters[$name] = ($this->rrCounters[$name] ?? 0) % count($endpoints);
        $chosenIp = $endpoints[$this->rrCounters[$name]];
        $this->rrCounters[$name]++;

        return [
            'target_ip'    => $chosenIp,
            'target_port'  => $targetPort,
            'service_name' => $service->getName(),
        ];
    }
}
