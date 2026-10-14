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

/**
 * Represents a cluster Node hosting pods.
 */
class Node
{
    /**
     * @param string $name Node identifier
     * @param string $internalIp Internal IPv4 address
     * @param string $status 'Ready' or 'NotReady'
     * @param array<string, string> $labels Key-value node labels
     * @param Taint[] $taints Taints applied to the node
     * @param ResourceRequirements|null $capacity Total physical node hardware capacity
     * @param ResourceRequirements|null $allocatable Available resource capacity for pods
     * @param ResourceRequirements|null $allocated Current sum of resource requests allocated to running pods
     * @param string[] $assignedPods List of pod names currently scheduled on this node
     */
    public function __construct(
        protected string $name,
        protected string $internalIp = '192.168.1.10',
        protected string $status = 'Ready',
        protected array $labels = [],
        protected array $taints = [],
        protected ?ResourceRequirements $capacity = null,
        protected ?ResourceRequirements $allocatable = null,
        protected ?ResourceRequirements $allocated = null,
        protected array $assignedPods = []
    ) {
        $this->capacity ??= new ResourceRequirements(4000, 8192); // 4 CPUs (4000m), 8Gi RAM (8192Mi)
        $this->allocatable ??= new ResourceRequirements(3800, 7680); // subtracting system reserved
        $this->allocated ??= new ResourceRequirements(0, 0);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInternalIp(): string
    {
        return $this->internalIp;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isReady(): bool
    {
        return $this->status === 'Ready';
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
     * @return Taint[]
     */
    public function getTaints(): array
    {
        return $this->taints;
    }

    public function addTaint(Taint $taint): self
    {
        $this->taints[] = $taint;
        return $this;
    }

    public function getCapacity(): ResourceRequirements
    {
        return $this->capacity ?? new ResourceRequirements(0, 0);
    }

    public function getAllocatable(): ResourceRequirements
    {
        return $this->allocatable ?? new ResourceRequirements(0, 0);
    }

    public function getAllocated(): ResourceRequirements
    {
        return $this->allocated ?? new ResourceRequirements(0, 0);
    }

    /**
     * @return string[]
     */
    public function getAssignedPods(): array
    {
        return $this->assignedPods;
    }

    /**
     * Compute remaining available resources on this node.
     */
    public function getAvailableResources(): ResourceRequirements
    {
        return $this->getAllocatable()->subtract($this->getAllocated());
    }

    /**
     * Check if node has sufficient free capacity to schedule the given pod requirements.
     */
    public function hasCapacity(ResourceRequirements $requirements): bool
    {
        return $this->getAvailableResources()->fits($requirements);
    }

    /**
     * Reserve resources for a newly scheduled pod.
     */
    public function allocate(ResourceRequirements $requirements, string $podName): bool
    {
        if (!$this->hasCapacity($requirements)) {
            return false;
        }

        $this->allocated = $this->getAllocated()->add($requirements);
        if (!in_array($podName, $this->assignedPods, true)) {
            $this->assignedPods[] = $podName;
        }

        return true;
    }

    /**
     * Release reserved resources when a pod terminates or is descheduled.
     */
    public function release(ResourceRequirements $requirements, string $podName): void
    {
        $this->allocated = $this->getAllocated()->subtract($requirements);
        $this->assignedPods = array_values(array_filter(
            $this->assignedPods,
            fn($name) => $name !== $podName
        ));
    }
}
