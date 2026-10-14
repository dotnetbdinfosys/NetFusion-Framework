<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos;

use Throwable;
use NetFusion\Core\Chaos\Experiment\ChaosExperiment;
use NetFusion\Core\Chaos\Experiment\ExperimentResult;
use NetFusion\Core\Chaos\Guardrails\EmergencyKillSwitch;
use NetFusion\Core\Chaos\Resiliency\ResiliencyScorer;

/**
 * Class ChaosOrchestrator
 *
 * Coordinates execution of Chaos Experiments, GameDays, blast radius enforcement, and rollback.
 */
class ChaosOrchestrator
{
    protected EmergencyKillSwitch $killSwitch;
    protected ?ResiliencyScorer $scorer = null;
    /** @var array<string, ExperimentResult> */
    protected array $executionHistory = [];

    public function __construct(?EmergencyKillSwitch $killSwitch = null, ?ResiliencyScorer $scorer = null)
    {
        $this->killSwitch = $killSwitch ?? EmergencyKillSwitch::getInstance();
        $this->scorer = $scorer;
    }

    public function getKillSwitch(): EmergencyKillSwitch
    {
        return $this->killSwitch;
    }

    public function getScorer(): ResiliencyScorer
    {
        if ($this->scorer === null) {
            $this->scorer = new ResiliencyScorer();
        }
        return $this->scorer;
    }

    public function setScorer(ResiliencyScorer $scorer): static
    {
        $this->scorer = $scorer;
        return $this;
    }

    /**
     * Run a chaos experiment end-to-end.
     */
    public function run(ChaosExperiment $experiment, array $context = []): ExperimentResult
    {
        $result = new ExperimentResult($experiment->getId(), $experiment->getName());
        $result->log("Starting Chaos Experiment: '{$experiment->getName()}' (ID: {$experiment->getId()})");

        // 1. Emergency Kill Switch Verification
        try {
            $this->killSwitch->assertSafe();
        } catch (Throwable $e) {
            $result->setStatus(ExperimentResult::STATUS_ABORTED);
            $result->setAbortReason($e->getMessage());
            $result->log("ABORTED: Kill switch active: " . $e->getMessage());
            $result->setResiliencyScore(0.0);
            $result->complete();
            $this->executionHistory[$experiment->getId()] = $result;
            return $result;
        }

        // 2. Blast Radius Guardrail Verification
        $policy = $experiment->getPolicy();
        if (!$policy->isPermitted($context)) {
            $rejection = $policy->getLastRejectionReason() ?? 'Blast radius limit breached';
            $result->setStatus(ExperimentResult::STATUS_BLAST_RADIUS_VIOLATION);
            $result->setAbortReason($rejection);
            $result->log("REJECTED: Blast radius check failed: {$rejection}");
            $result->setResiliencyScore(0.0);
            $result->complete();
            $this->executionHistory[$experiment->getId()] = $result;
            return $result;
        }
        $result->log("Blast radius check PASSED.");

        // 3. Pre-Flight Steady State Hypothesis Evaluation
        $hypothesis = $experiment->getHypothesis();
        $preMetrics = $context['pre_metrics'] ?? ['error_rate' => 0.0, 'p99_latency_ms' => 50.0, 'success_rate' => 100.0];
        $isPreHealthy = $hypothesis->evaluate($preMetrics, $context);
        $result->setSteadyStatePre($isPreHealthy);

        if (!$isPreHealthy) {
            $result->setStatus(ExperimentResult::STATUS_ABORTED);
            $result->setViolations($hypothesis->getViolations());
            $result->setAbortReason("Pre-flight steady-state check failed. System is not currently in a healthy state.");
            $result->log("ABORTED: Pre-flight steady-state violation: " . implode('; ', $hypothesis->getViolations()));
            $result->setResiliencyScore(0.0);
            $result->complete();
            $this->executionHistory[$experiment->getId()] = $result;
            return $result;
        }
        $result->log("Pre-flight steady-state verified healthy.");

        // 4. Inject Faults & Execute Probe
        $injectors = $experiment->getInjectors();
        $action = $experiment->getAction();
        $actionError = null;
        $activeInjectors = [];

        try {
            foreach ($injectors as $injector) {
                if ($injector->shouldTrigger($context)) {
                    $activeInjectors[] = $injector;
                    $result->log("Triggering injector: '{$injector->getName()}'");
                    $injectOutcome = $injector->inject($context);
                    $result->recordFault($injector->getName(), $injectOutcome);
                } else {
                    $result->log("Injector '{$injector->getName()}' condition not triggered or rolled false.");
                }
            }

            // Execute target workload / action under chaos conditions
            if ($action !== null) {
                $result->log("Executing workload action under active chaos injection...");
                $actionResult = $action($context);
                $result->setActionResult($actionResult);
                $result->log("Workload action executed successfully.");
            }
        } catch (Throwable $e) {
            $actionError = $e;
            $result->log("Caught exception during chaos execution: " . get_class($e) . " - " . $e->getMessage());
            // If the experiment expected an exception or handled via graceful degradation:
            $result->setActionResult(['error' => $e->getMessage(), 'class' => get_class($e)]);
        } finally {
            // 5. Always Rollback Active Injectors & Call Rollback Routine
            $result->log("Rolling back active injectors...");
            foreach ($activeInjectors as $injector) {
                try {
                    $injector->rollback();
                } catch (Throwable $rollbackEx) {
                    $result->log("Warning: Rollback on '{$injector->getName()}' threw: " . $rollbackEx->getMessage());
                }
            }

            if ($experiment->getRollbackAction() !== null) {
                try {
                    ($experiment->getRollbackAction())($context);
                } catch (Throwable $customRbEx) {
                    $result->log("Warning: Custom rollback action threw: " . $customRbEx->getMessage());
                }
            }
            $result->log("Rollback completed.");
        }

        // 6. Post-Experiment Steady State Hypothesis Evaluation
        $postMetrics = $context['post_metrics'] ?? ['error_rate' => 0.0, 'p99_latency_ms' => 60.0, 'success_rate' => 100.0];
        $isPostHealthy = $hypothesis->evaluate($postMetrics, $context);
        $result->setSteadyStatePost($isPostHealthy);

        if (!$isPostHealthy) {
            $result->setViolations($hypothesis->getViolations());
            $result->log("Post-experiment steady-state violations detected: " . implode('; ', $hypothesis->getViolations()));
        } else {
            $result->log("Post-experiment steady-state successfully recovered.");
        }

        // 7. Calculate Resiliency Score
        $scorer = $this->getScorer();
        $score = $scorer->calculateScore($result, $actionError, $context);
        $result->setResiliencyScore($score);

        // 8. Determine Final Status
        if ($isPostHealthy && ($actionError === null || ($context['expect_exception'] ?? false))) {
            $result->setStatus(ExperimentResult::STATUS_SUCCESS);
        } else {
            $result->setStatus(ExperimentResult::STATUS_FAILED);
        }

        $result->complete();
        $result->log("Experiment finished with status {$result->getStatus()}, Resiliency Score: {$result->getResiliencyScore()}/100");

        $this->executionHistory[$experiment->getId()] = $result;
        return $result;
    }

    /**
     * Trigger global emergency abort.
     */
    public function abortAll(string $reason = 'Emergency GameDay Abort', ?string $initiatedBy = 'operator'): void
    {
        $this->killSwitch->activate($reason, $initiatedBy);
    }

    /**
     * Get recent experiment executions.
     *
     * @return array<string, ExperimentResult>
     */
    public function getExecutionHistory(): array
    {
        return $this->executionHistory;
    }
}
