<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/SelfHealingOrchestrator.php
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
use NetFusion\Core\Infrastructure\SelfHealing\Enums\IncidentSeverity;
use NetFusion\Core\Infrastructure\SelfHealing\Enums\RemediationStatus;
use NetFusion\Core\Infrastructure\SelfHealing\Remediation\BlastRadiusPolicy;
use NetFusion\Core\Infrastructure\SelfHealing\Remediation\CanaryExecutor;
use NetFusion\Core\Infrastructure\SelfHealing\Remediation\RemediationAction;
use NetFusion\Core\Infrastructure\SelfHealing\Telemetry\AnomalyEvent;
use NetFusion\Core\Infrastructure\SelfHealing\Telemetry\HealthSignal;
use NetFusion\Core\Infrastructure\SelfHealing\Telemetry\MetricAnomalyDetector;

/**
 * Autonomous Self-Healing Infrastructure Master Orchestrator.
 * Coordinates Telemetry Anomaly Detection -> Causal RCA -> Blast-Radius Guardrails -> Canary Rollout.
 */
class SelfHealingOrchestrator
{
    /** @var AnomalyEvent[] In-memory incident buffer */
    private array $activeAnomalies = [];

    /** @var RootCauseReport[] In-memory historical reports */
    private array $reports = [];

    /** @var RemediationAction[] In-memory action records */
    private array $actions = [];

    public function __construct(
        private MetricAnomalyDetector $anomalyDetector,
        private DependencyGraphAnalyzer $graphAnalyzer,
        private HypothesisRanker $hypothesisRanker,
        private BlastRadiusPolicy $blastRadiusPolicy,
        private CanaryExecutor $canaryExecutor
    ) {
    }

    public function getAnomalyDetector(): MetricAnomalyDetector
    {
        return $this->anomalyDetector;
    }

    public function getGraphAnalyzer(): DependencyGraphAnalyzer
    {
        return $this->graphAnalyzer;
    }

    public function getHypothesisRanker(): HypothesisRanker
    {
        return $this->hypothesisRanker;
    }

    public function getBlastRadiusPolicy(): BlastRadiusPolicy
    {
        return $this->blastRadiusPolicy;
    }

    public function getCanaryExecutor(): CanaryExecutor
    {
        return $this->canaryExecutor;
    }

    /**
     * Ingest health signals and detect metric anomalies.
     *
     * @param HealthSignal[] $signals
     * @return AnomalyEvent[]
     */
    public function ingestSignals(array $signals): array
    {
        $events = $this->anomalyDetector->detect($signals);
        foreach ($events as $event) {
            $this->activeAnomalies[] = $event;
        }
        return $events;
    }

    /**
     * Perform automated Root-Cause Analysis (RCA) on anomalous events.
     *
     * @param AnomalyEvent[] $anomalies
     */
    public function diagnose(array $anomalies): RootCauseReport
    {
        $services = array_map(fn(AnomalyEvent $e) => $e->getServiceName(), $anomalies);
        $causalResult = $this->graphAnalyzer->findCausalPath($services);
        $primaryCulprit = $causalResult['primary_culprit'];
        $causalChain = $causalResult['causal_chain'];

        // Filter anomalies pertaining to culprit or causal chain
        $culpritAnomalies = array_filter(
            $anomalies,
            fn(AnomalyEvent $e) => $e->getServiceName() === $primaryCulprit
        );
        if (empty($culpritAnomalies)) {
            $culpritAnomalies = $anomalies;
        }

        $rankResult = $this->hypothesisRanker->rank($culpritAnomalies);

        // Highest severity among culprit anomalies
        $highestSeverity = IncidentSeverity::LOW;
        foreach ($culpritAnomalies as $event) {
            if ($event->getSeverity()->priority() > $highestSeverity->priority()) {
                $highestSeverity = $event->getSeverity();
            }
        }

        $reportId = 'rca_' . substr(md5($primaryCulprit . microtime(true)), 0, 10);
        $report = new RootCauseReport(
            $reportId,
            $primaryCulprit,
            $rankResult['primary_hypothesis'],
            $rankResult['confidence'],
            $causalChain,
            $rankResult['hypotheses'],
            $rankResult['recommended_action'],
            $highestSeverity
        );

        $this->reports[$reportId] = $report;
        return $report;
    }

    /**
     * Execute safe, canary-controlled remediation based on an RCA report.
     */
    public function remediate(RootCauseReport $report, ?callable $healthProbe = null, int $fleetSize = 10): RemediationAction
    {
        $actionId = 'act_' . substr(md5($report->getReportId() . microtime(true)), 0, 10);
        $action = new RemediationAction(
            $actionId,
            $report->getPrimaryCulpritService(),
            $report->getRecommendedAction(),
            [
                'rca_id'     => $report->getReportId(),
                'hypothesis' => $report->getPrimaryHypothesis(),
                'severity'   => $report->getSeverity()->value,
            ]
        );

        // Validate blast radius guardrails
        $this->blastRadiusPolicy->validate($action, $fleetSize);
        $this->blastRadiusPolicy->recordActionStart($action->getTargetService());

        try {
            $this->canaryExecutor->execute($action, $healthProbe);
        } finally {
            $this->blastRadiusPolicy->recordActionEnd();
        }

        $this->actions[$actionId] = $action;
        return $action;
    }

    /**
     * End-to-end autonomous healing loop: Detect -> Diagnose -> Remediate.
     *
     * @param HealthSignal[] $signals
     * @return array{
     *   anomalies: AnomalyEvent[],
     *   report: ?RootCauseReport,
     *   action: ?RemediationAction
     * }
     */
    public function autoHeal(array $signals, ?callable $healthProbe = null, int $fleetSize = 10): array
    {
        $anomalies = $this->ingestSignals($signals);
        if (empty($anomalies)) {
            return [
                'anomalies' => [],
                'report'    => null,
                'action'    => null,
            ];
        }

        $report = $this->diagnose($anomalies);
        $action = $this->remediate($report, $healthProbe, $fleetSize);

        return [
            'anomalies' => $anomalies,
            'report'    => $report,
            'action'    => $action,
        ];
    }

    /**
     * @return AnomalyEvent[]
     */
    public function getActiveAnomalies(): array
    {
        return $this->activeAnomalies;
    }

    /**
     * @return RootCauseReport[]
     */
    public function getReports(): array
    {
        return $this->reports;
    }

    /**
     * @return RemediationAction[]
     */
    public function getActions(): array
    {
        return $this->actions;
    }
}
