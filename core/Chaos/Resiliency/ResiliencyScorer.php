<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Resiliency;

use Throwable;
use NetFusion\Core\Chaos\Experiment\ExperimentResult;

/**
 * Class ResiliencyScorer
 *
 * Computes a multi-dimensional 0–100 System Resiliency Score, letter grade,
 * and automated architectural recommendations.
 */
class ResiliencyScorer
{
    protected float $steadyStateWeight = 0.30;
    protected float $containmentWeight = 0.25;
    protected float $latencyWeight = 0.20;
    protected float $guardrailWeight = 0.15;
    protected float $recoverySpeedWeight = 0.10;

    /**
     * Calculate resiliency score from an experiment execution.
     */
    public function calculateScore(ExperimentResult $result, ?Throwable $exception = null, array $context = []): float
    {
        // If aborted or blast radius violation, score is severely penalised
        if ($result->getStatus() === ExperimentResult::STATUS_BLAST_RADIUS_VIOLATION) {
            return 10.0;
        }
        if ($result->getStatus() === ExperimentResult::STATUS_ABORTED) {
            return 20.0;
        }

        // 1. Steady State Sub-score (0-100)
        $steadyStateScore = 0.0;
        if ($result->isSteadyStatePre()) {
            $steadyStateScore += 50.0;
        }
        if ($result->isSteadyStatePost()) {
            $steadyStateScore += 50.0;
        }
        // Deduct for violations
        $violationPenalty = count($result->getViolations()) * 20.0;
        $steadyStateScore = max(0.0, $steadyStateScore - $violationPenalty);

        // 2. Containment & Degradation Sub-score (0-100)
        $containmentScore = 100.0;
        if ($exception !== null) {
            $expected = (bool) ($context['expect_exception'] ?? false);
            $handled = (bool) ($context['fallback_invoked'] ?? false) || (bool) ($context['circuit_breaker_opened'] ?? false);
            if ($handled || $expected) {
                $containmentScore = 85.0; // Gracefully caught by resilience pattern
            } else {
                $containmentScore = 30.0; // Unhandled exception breached boundary
            }
        }

        // 3. Latency Tolerance Sub-score (0-100)
        $latencyScore = 100.0;
        $maxToleranceMs = (float) ($context['max_latency_tolerance_ms'] ?? 500.0);
        $durationMs = $result->getDurationMs();
        if ($durationMs > $maxToleranceMs) {
            $overageRatio = ($durationMs - $maxToleranceMs) / $maxToleranceMs;
            $latencyScore = max(20.0, 100.0 - ($overageRatio * 50.0));
        }

        // 4. Guardrail Compliance Sub-score (0-100)
        $guardrailScore = 100.0;
        if (!empty($result->getAbortReason())) {
            $guardrailScore = 50.0;
        }

        // 5. Recovery Speed Sub-score (0-100)
        $recoveryScore = 100.0;
        if ($durationMs > 2000.0) {
            $recoveryScore = 70.0;
        }

        // Weighted total
        $total = ($steadyStateScore * $this->steadyStateWeight)
            + ($containmentScore * $this->containmentWeight)
            + ($latencyScore * $this->latencyWeight)
            + ($guardrailScore * $this->guardrailWeight)
            + ($recoveryScore * $this->recoverySpeedWeight);

        $finalScore = round(max(0.0, min(100.0, $total)), 2);
        $result->setResiliencyScore($finalScore);

        return $finalScore;
    }

    /**
     * Map numerical score to letter grade.
     */
    public function getGrade(float $score): string
    {
        return match (true) {
            $score >= 95.0 => 'A+',
            $score >= 90.0 => 'A',
            $score >= 80.0 => 'B',
            $score >= 70.0 => 'C',
            $score >= 60.0 => 'D',
            default => 'F',
        };
    }

    /**
     * Generate automated recommendations based on score and violations.
     */
    public function generateRecommendations(ExperimentResult $result): array
    {
        $recommendations = [];
        $score = $result->getResiliencyScore();

        if (!$result->isSteadyStatePost()) {
            $recommendations[] = "System did not restore to steady state after fault withdrawal. Implement automatic self-healing or connection pool recycling.";
        }

        if (count($result->getViolations()) > 0) {
            $recommendations[] = "Steady-state hypothesis thresholds breached: review retry limits and downstream timeout configurations.";
        }

        if ($score < 70.0) {
            $recommendations[] = "Resiliency score below target (70+). Add circuit breaker protection and fallback responses for critical dependencies.";
        }

        if ($result->getDurationMs() > 1000.0) {
            $recommendations[] = "Fault recovery duration exceeded 1000ms. Consider aggressive timeout tuning and background health check probing.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "Resiliency posture is optimal. Continue periodic GameDay drills to prevent regression.";
        }

        return $recommendations;
    }
}
