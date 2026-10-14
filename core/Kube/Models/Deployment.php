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
 * Represents a Kubernetes Deployment providing declarative updates for Pods.
 */
class Deployment
{
    /**
     * @param string $name Deployment name
     * @param string $namespace Namespace
     * @param int $replicas Desired replica count
     * @param array<string, string> $matchLabels Pod selector labels
     * @param array<string, mixed> $template Pod template specification
     * @param string $strategy 'RollingUpdate' or 'Recreate'
     * @param int|string $maxSurge Maximum extra pods during rolling update
     * @param int|string $maxUnavailable Maximum unavailable pods during update
     * @param int $generation Configuration generation
     * @param int $readyReplicas Number of ready pods
     * @param int $availableReplicas Number of available pods
     */
    public function __construct(
        protected string $name,
        protected string $namespace = 'default',
        protected int $replicas = 1,
        protected array $matchLabels = [],
        protected array $template = [],
        protected string $strategy = 'RollingUpdate',
        protected int|string $maxSurge = '25%',
        protected int|string $maxUnavailable = '25%',
        protected int $generation = 1,
        protected int $readyReplicas = 0,
        protected int $availableReplicas = 0
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

    public function getReplicas(): int
    {
        return $this->replicas;
    }

    public function setReplicas(int $replicas): self
    {
        $this->replicas = max(0, $replicas);
        $this->generation++;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getMatchLabels(): array
    {
        return $this->matchLabels;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplate(): array
    {
        return $this->template;
    }

    public function setTemplate(array $template): self
    {
        $this->template = $template;
        $this->generation++;
        return $this;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function getMaxSurge(): int|string
    {
        return $this->maxSurge;
    }

    public function getMaxUnavailable(): int|string
    {
        return $this->maxUnavailable;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function getReadyReplicas(): int
    {
        return $this->readyReplicas;
    }

    public function setReadyReplicas(int $count): self
    {
        $this->readyReplicas = $count;
        return $this;
    }

    public function getAvailableReplicas(): int
    {
        return $this->availableReplicas;
    }

    public function setAvailableReplicas(int $count): self
    {
        $this->availableReplicas = $count;
        return $this;
    }

    /**
     * Check if a Pod belongs to this Deployment by matching labels.
     */
    public function matchesPod(Pod $pod): bool
    {
        if ($pod->getNamespace() !== $this->namespace) {
            return false;
        }

        $podLabels = $pod->getLabels();
        foreach ($this->matchLabels as $key => $value) {
            if (!isset($podLabels[$key]) || $podLabels[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
