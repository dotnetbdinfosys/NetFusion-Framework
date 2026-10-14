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

namespace NetFusion\Core\Kube\Models;

use NetFusion\Core\Kube\Enums\ServiceType;

/**
 * Represents a Kubernetes Service abstracting access to a set of Pods.
 */
class Service
{
    /**
     * @param string $name Service identifier
     * @param string $namespace Namespace
     * @param ServiceType $type ClusterIP, NodePort, LoadBalancer
     * @param string|null $clusterIp Virtual IP assigned to the service
     * @param array<array{name?: string, port: int, targetPort: int, protocol?: string, nodePort?: int}> $ports Exposed ports
     * @param array<string, string> $selector Pod selector labels
     * @param string[] $endpoints List of backend ready Pod IP addresses
     */
    public function __construct(
        protected string $name,
        protected string $namespace = 'default',
        protected ServiceType $type = ServiceType::CLUSTER_IP,
        protected ?string $clusterIp = null,
        protected array $ports = [],
        protected array $selector = [],
        protected array $endpoints = []
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getType(): ServiceType
    {
        return $this->type;
    }

    public function getClusterIp(): ?string
    {
        return $this->clusterIp;
    }

    public function setClusterIp(?string $clusterIp): self
    {
        $this->clusterIp = $clusterIp;
        return $this;
    }

    /**
     * @return array<array{name?: string, port: int, targetPort: int, protocol?: string, nodePort?: int}>
     */
    public function getPorts(): array
    {
        return $this->ports;
    }

    /**
     * @return array<string, string>
     */
    public function getSelector(): array
    {
        return $this->selector;
    }

    /**
     * @return string[]
     */
    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * @param string[] $endpoints
     */
    public function setEndpoints(array $endpoints): self
    {
        $this->endpoints = array_values(array_unique($endpoints));
        return $this;
    }

    public function addEndpoint(string $ip): self
    {
        if (!in_array($ip, $this->endpoints, true)) {
            $this->endpoints[] = $ip;
        }
        return $this;
    }

    public function removeEndpoint(string $ip): self
    {
        $this->endpoints = array_values(array_filter(
            $this->endpoints,
            fn($endpoint) => $endpoint !== $ip
        ));
        return $this;
    }

    /**
     * Check if a Pod matches this Service's selector.
     */
    public function matchesPod(Pod $pod): bool
    {
        if ($pod->getNamespace() !== $this->namespace) {
            return false;
        }

        if (empty($this->selector)) {
            return false;
        }

        $podLabels = $pod->getLabels();
        foreach ($this->selector as $key => $value) {
            if (!isset($podLabels[$key]) || $podLabels[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
