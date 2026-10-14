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

use NetFusion\Core\Kube\Enums\ContainerState;
use NetFusion\Core\Kube\Enums\PodPhase;
use NetFusion\Core\Kube\Enums\RestartPolicy;

/**
 * Represents a Kubernetes Pod, the smallest deployable computing unit.
 */
class Pod
{
    /**
     * @param string $name Pod name
     * @param string $namespace Namespace (default: 'default')
     * @param string|null $uid Unique pod identifier
     * @param array<string, string> $labels Key-value labels
     * @param array<string, string> $annotations Key-value annotations
     * @param string|null $nodeName Name of node the pod is scheduled onto
     * @param PodPhase $phase Current lifecycle phase
     * @param string|null $ip Pod virtual IP address
     * @param ContainerSpec[] $containers Container specifications
     * @param array<string, ContainerStatus> $containerStatuses Container runtime statuses
     * @param RestartPolicy $restartPolicy Container restart policy
     * @param array<string, string> $nodeSelector Node label selector requirements
     * @param Toleration[] $tolerations Taint tolerations
     * @param array<string, mixed> $volumes Defined pod volumes
     * @param float|null $creationTimestamp
     */
    public function __construct(
        protected string $name,
        protected string $namespace = 'default',
        protected ?string $uid = null,
        protected array $labels = [],
        protected array $annotations = [],
        protected ?string $nodeName = null,
        protected PodPhase $phase = PodPhase::PENDING,
        protected ?string $ip = null,
        protected array $containers = [],
        protected array $containerStatuses = [],
        protected RestartPolicy $restartPolicy = RestartPolicy::ALWAYS,
        protected array $nodeSelector = [],
        protected array $tolerations = [],
        protected array $volumes = [],
        protected ?float $creationTimestamp = null
    ) {
        $this->uid ??= 'pod-' . bin2hex(random_bytes(6));
        $this->creationTimestamp ??= microtime(true);

        // Initialize status for each container if not provided
        foreach ($this->containers as $spec) {
            if (!isset($this->containerStatuses[$spec->getName()])) {
                $this->containerStatuses[$spec->getName()] = new ContainerStatus(
                    $spec->getName(),
                    ContainerState::WAITING
                );
            }
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getUid(): string
    {
        return $this->uid ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function getLabel(string $key, ?string $default = null): ?string
    {
        return $this->labels[$key] ?? $default;
    }

    public function setLabel(string $key, string $value): self
    {
        $this->labels[$key] = $value;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function getNodeName(): ?string
    {
        return $this->nodeName;
    }

    public function setNodeName(?string $nodeName): self
    {
        $this->nodeName = $nodeName;
        return $this;
    }

    public function getPhase(): PodPhase
    {
        return $this->phase;
    }

    public function setPhase(PodPhase $phase): self
    {
        $this->phase = $phase;
        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return ContainerSpec[]
     */
    public function getContainers(): array
    {
        return $this->containers;
    }

    public function getContainer(string $name): ?ContainerSpec
    {
        foreach ($this->containers as $c) {
            if ($c->getName() === $name) {
                return $c;
            }
        }
        return null;
    }

    /**
     * @return array<string, ContainerStatus>
     */
    public function getContainerStatuses(): array
    {
        return $this->containerStatuses;
    }

    public function getContainerStatus(string $name): ?ContainerStatus
    {
        return $this->containerStatuses[$name] ?? null;
    }

    public function getRestartPolicy(): RestartPolicy
    {
        return $this->restartPolicy;
    }

    /**
     * @return array<string, string>
     */
    public function getNodeSelector(): array
    {
        return $this->nodeSelector;
    }

    /**
     * @return Toleration[]
     */
    public function getTolerations(): array
    {
        return $this->tolerations;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVolumes(): array
    {
        return $this->volumes;
    }

    public function getCreationTimestamp(): float
    {
        return $this->creationTimestamp ?? 0.0;
    }

    /**
     * Calculate aggregated resource requirements across all containers.
     */
    public function getTotalResources(): ResourceRequirements
    {
        $total = new ResourceRequirements(0, 0);
        foreach ($this->containers as $container) {
            $total = $total->add($container->getResources());
        }
        return $total;
    }

    /**
     * Check if all containers inside the pod are ready.
     */
    public function isReady(): bool
    {
        if ($this->phase !== PodPhase::RUNNING) {
            return false;
        }

        if (empty($this->containerStatuses)) {
            return false;
        }

        foreach ($this->containerStatuses as $status) {
            if (!$status->isReady()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if pod has reached terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->phase->isTerminal();
    }
}
