<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/SelfHealingManager.php
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

namespace NetFusion\Core\Infrastructure\SelfHealing;

use NetFusion\Core\Infrastructure\SelfHealing\Analysis\DependencyGraphAnalyzer;
use NetFusion\Core\Infrastructure\SelfHealing\Analysis\HypothesisRanker;
use NetFusion\Core\Infrastructure\SelfHealing\Analysis\RootCauseReport;
use NetFusion\Core\Infrastructure\SelfHealing\Enums\ActionType;
use NetFusion\Core\Infrastructure\SelfHealing\Remediation\BlastRadiusPolicy;
use NetFusion\Core\Infrastructure\SelfHealing\Remediation\CanaryExecutor;
use NetFusion\Core\Infrastructure\SelfHealing\Remediation\RemediationAction;
use NetFusion\Core\Infrastructure\SelfHealing\Telemetry\HealthSignal;
use NetFusion\Core\Infrastructure\SelfHealing\Telemetry\MetricAnomalyDetector;

/**
 * High-Level Gateway Manager for Autonomous Self-Healing Infrastructure.
 */
class SelfHealingManager
{
    private static ?self $instance = null;
    private SelfHealingOrchestrator $orchestrator;

    public function __construct(?SelfHealingOrchestrator $orchestrator = null)
    {
        if ($orchestrator !== null) {
            $this->orchestrator = $orchestrator;
        } else {
            $detector = new MetricAnomalyDetector(windowSize: 50, zThreshold: 3.0, minSamples: 5);
            $graph = new DependencyGraphAnalyzer();
            $ranker = new HypothesisRanker();
            $policy = new BlastRadiusPolicy(maxDisruptedPercent: 25.0, maxConcurrentActions: 2, cooldownSeconds: 60);
            $executor = new CanaryExecutor(steps: [10.0, 50.0, 100.0]);

            $this->orchestrator = new SelfHealingOrchestrator($detector, $graph, $ranker, $policy, $executor);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getOrchestrator(): SelfHealingOrchestrator
    {
        return $this->orchestrator;
    }

    /**
     * Record metric signal for a service.
     */
    public function recordSignal(string $service, string $metric, float $value, ?int $timestamp = null): ?Telemetry\AnomalyEvent
    {
        return $this->orchestrator->getAnomalyDetector()->record($service, $metric, $value, $timestamp);
    }

    /**
     * Add service dependency.
     */
    public function addDependency(string $caller, string $callee): self
    {
        $this->orchestrator->getGraphAnalyzer()->addDependency($caller, $callee);
        return $this;
    }

    /**
     * Run root-cause diagnosis.
     */
    public function diagnose(array $anomalies): RootCauseReport
    {
        return $this->orchestrator->diagnose($anomalies);
    }

    /**
     * Trigger canary remediation.
     */
    public function remediate(RootCauseReport $report, ?callable $healthProbe = null, int $fleetSize = 10): RemediationAction
    {
        return $this->orchestrator->remediate($report, $healthProbe, $fleetSize);
    }

    /**
     * Autonomous healing loop: Ingest signals and apply remediation.
     */
    public function autoHeal(array $signals, ?callable $healthProbe = null, int $fleetSize = 10): array
    {
        return $this->orchestrator->autoHeal($signals, $healthProbe, $fleetSize);
    }
}
