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

namespace NetFusion\Core\Kube\Autoscaler;

use NetFusion\Core\Kube\Models\Deployment;

/**
 * Horizontal Pod Autoscaler (HPA) automatically scaling deployment replicas based on metrics.
 */
class HorizontalPodAutoscaler
{
    protected ?float $lastScaleTime = null;

    /**
     * @param string $deploymentName Name of the deployment to autoscale
     * @param int $minReplicas Lower limit for replica count
     * @param int $maxReplicas Upper limit for replica count
     * @param float $targetUtilization Target utilization percentage (e.g. 70.0 for 70%)
     * @param int $cooldownSeconds Cooldown window between scale events to prevent thrashing
     */
    public function __construct(
        protected string $deploymentName,
        protected int $minReplicas = 1,
        protected int $maxReplicas = 10,
        protected float $targetUtilization = 70.0,
        protected int $cooldownSeconds = 15
    ) {
    }

    public function getDeploymentName(): string
    {
        return $this->deploymentName;
    }

    public function getMinReplicas(): int
    {
        return $this->minReplicas;
    }

    public function getMaxReplicas(): int
    {
        return $this->maxReplicas;
    }

    public function getTargetUtilization(): float
    {
        return $this->targetUtilization;
    }

    /**
     * Evaluate current metric value and compute desired replica count according to Kubernetes HPA algorithm.
     * Formula: desiredReplicas = ceil[currentReplicas * (currentMetricValue / targetMetricValue)]
     *
     * @param Deployment $deployment
     * @param float $currentUtilization Observed utilization (e.g. 85.0 for 85%)
     * @param bool $applyIfChanged If true, immediately modifies deployment->setReplicas()
     * @return int Computed desired replica count
     */
    public function evaluate(Deployment $deployment, float $currentUtilization, bool $applyIfChanged = true): int
    {
        $currentReplicas = max(1, $deployment->getReplicas());
        $now = microtime(true);

        // Check cooldown window
        if ($this->lastScaleTime !== null && ($now - $this->lastScaleTime) < $this->cooldownSeconds) {
            return $currentReplicas;
        }

        // Standard Kubernetes HPA tolerance band (0.10 / 10%)
        $ratio = $currentUtilization / max(1.0, $this->targetUtilization);
        if (abs(1.0 - $ratio) <= 0.10) {
            // Within 10% tolerance, do not scale
            return $currentReplicas;
        }

        $computed = (int) ceil($currentReplicas * $ratio);
        $desired = max($this->minReplicas, min($this->maxReplicas, $computed));

        if ($desired !== $currentReplicas) {
            $this->lastScaleTime = $now;
            if ($applyIfChanged) {
                $deployment->setReplicas($desired);
            }
        }

        return $desired;
    }
}
