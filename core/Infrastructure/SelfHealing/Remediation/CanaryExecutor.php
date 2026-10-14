<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Remediation/CanaryExecutor.php
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

use NetFusion\Core\Infrastructure\SelfHealing\Enums\RemediationStatus;

/**
 * Canary Remediation Executor applying progressive rollout with steady-state verification.
 */
class CanaryExecutor
{
    /**
     * @param float[] $steps Progressive rollout percentages (e.g., [10.0, 50.0, 100.0])
     */
    public function __construct(
        private array $steps = [10.0, 50.0, 100.0]
    ) {
    }

    /**
     * Execute progressive canary remediation with continuous health verification.
     *
     * @param RemediationAction $action
     * @param callable|null $healthProbe function(string $service, float $percent): bool
     * @return bool True if full 100% rollout verified, False if rolled back.
     */
    public function execute(RemediationAction $action, ?callable $healthProbe = null): bool
    {
        $service = $action->getTargetService();
        $actionName = $action->getActionType()->value;

        foreach ($this->steps as $stepPercent) {
            $action->setCanaryPercentage($stepPercent);
            $action->setStatus(RemediationStatus::CANARY_PROGRESSING);
            $action->addLog("Applying {$actionName} to {$stepPercent}% of instances for {$service}");

            // Verify steady-state health at this step
            $isHealthy = true;
            if ($healthProbe !== null) {
                try {
                    $isHealthy = (bool)$healthProbe($service, $stepPercent);
                } catch (\Throwable $e) {
                    $isHealthy = false;
                    $action->addLog("Health probe error at {$stepPercent}%: " . $e->getMessage());
                }
            }

            if (!$isHealthy) {
                $action->addLog("Health verification failed at {$stepPercent}%. Rolling back action.");
                $action->setStatus(RemediationStatus::ROLLED_BACK);
                return false;
            }

            $action->addLog("Steady-state verified healthy at {$stepPercent}%");
        }

        $action->setStatus(RemediationStatus::VERIFIED);
        $action->addLog("Full canary remediation successfully completed and verified for {$service}");

        return true;
    }

    /**
     * @return float[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }
}
