<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Remediation/BlastRadiusPolicy.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Infrastructure\SelfHealing\Remediation;

use NetFusion\Core\Infrastructure\SelfHealing\Exceptions\BlastRadiusExceededException;

/**
 * Blast Radius Policy Guardrails to prevent catastrophic cascading disruptions during automated remediation.
 */
class BlastRadiusPolicy
{
    /** @var array<string, int> Last action timestamp per service */
    private array $serviceLastActionTime = [];

    /** @var int Currently active concurrent remediation actions */
    private int $activeActionCount = 0;

    public function __construct(
        private float $maxDisruptedPercent = 25.0,
        private int $maxConcurrentActions = 2,
        private int $cooldownSeconds = 60
    ) {
    }

    /**
     * Validate whether an intended remediation action satisfies safety guardrails.
     *
     * @throws BlastRadiusExceededException
     */
    public function validate(RemediationAction $action, int $totalFleetInstances = 10): void
    {
        $service = $action->getTargetService();
        $now = time();

        // 1. Check cooldown period
        if (isset($this->serviceLastActionTime[$service])) {
            $elapsed = $now - $this->serviceLastActionTime[$service];
            if ($elapsed < $this->cooldownSeconds) {
                $wait = $this->cooldownSeconds - $elapsed;
                throw new BlastRadiusExceededException(
                    "Service '{$service}' is in cooldown period. Please wait {$wait}s before attempting remediation."
                );
            }
        }

        // 2. Check maximum concurrent actions
        if ($this->activeActionCount >= $this->maxConcurrentActions) {
            throw new BlastRadiusExceededException(
                "Maximum concurrent remediation limit ({$this->maxConcurrentActions}) reached. Aborting to limit blast radius."
            );
        }

        // 3. Check percentage disruption limit
        if ($action->getActionType()->isDisruptive() && $totalFleetInstances > 0) {
            $canaryInitial = $action->getCanaryPercentage() > 0 ? $action->getCanaryPercentage() : 10.0;
            if ($canaryInitial > $this->maxDisruptedPercent) {
                throw new BlastRadiusExceededException(
                    "Action disruption request ({$canaryInitial}%) exceeds maximum allowable blast radius limit ({$this->maxDisruptedPercent}%)."
                );
            }
        }
    }

    /**
     * Mark an action as actively started.
     */
    public function recordActionStart(string $service): void
    {
        $this->activeActionCount++;
        $this->serviceLastActionTime[$service] = time();
    }

    /**
     * Mark an action as finished.
     */
    public function recordActionEnd(): void
    {
        if ($this->activeActionCount > 0) {
            $this->activeActionCount--;
        }
    }

    public function getMaxDisruptedPercent(): float
    {
        return $this->maxDisruptedPercent;
    }

    public function getMaxConcurrentActions(): int
    {
        return $this->maxConcurrentActions;
    }

    public function getCooldownSeconds(): int
    {
        return $this->cooldownSeconds;
    }
}
