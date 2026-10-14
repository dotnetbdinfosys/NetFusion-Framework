<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Data\TimeSeries\Scaling;

use NetFusion\Core\Data\TimeSeries\TimeSeries;

class AutoScaler
{
    /** @var array<string, ScalingPolicy> */
    protected array $policies = [];

    /** @var array<string, float> Last scaling timestamp per policy */
    protected array $lastScaledAt = [];

    public function addPolicy(ScalingPolicy $policy): self
    {
        $this->policies[$policy->name] = $policy;
        return $this;
    }

    public function getPolicy(string $name): ?ScalingPolicy
    {
        return $this->policies[$name] ?? null;
    }

    /**
     * @return array<string, ScalingPolicy>
     */
    public function getPolicies(): array
    {
        return $this->policies;
    }

    /**
     * Evaluate a policy against a time series metric stream and current replica count.
     */
    public function evaluate(string $policyName, TimeSeries $series, int $currentReplicas): ScalingDecision
    {
        $policy = $this->getPolicy($policyName);
        if (!$policy) {
            return new ScalingDecision(
                policyName: $policyName,
                action: ScalingDecision::ACTION_MAINTAIN,
                currentReplicas: $currentReplicas,
                desiredReplicas: $currentReplicas,
                currentMetricValue: 0.0,
                predictedMetricValue: 0.0,
                reason: "Policy '{$policyName}' not found."
            );
        }

        $latestPoint = $series->latest();
        $currentValue = $latestPoint?->value ?? 0.0;

        // Forecast future metric value using Linear Regression
        $forecast = LinearRegressionPredictor::forecast($series, (float)$policy->forecastHorizonSeconds);
        $predictedValue = $forecast['predicted_value'];

        // Check cooldown
        $now = microtime(true);
        $lastScaled = $this->lastScaledAt[$policyName] ?? 0.0;
        if (($now - $lastScaled) < $policy->cooldownSeconds) {
            $remaining = (int)ceil($policy->cooldownSeconds - ($now - $lastScaled));
            return new ScalingDecision(
                policyName: $policyName,
                action: ScalingDecision::ACTION_COOLDOWN,
                currentReplicas: $currentReplicas,
                desiredReplicas: $currentReplicas,
                currentMetricValue: $currentValue,
                predictedMetricValue: $predictedValue,
                reason: "Policy in cooldown ({$remaining}s remaining)."
            );
        }

        // Target replica calculation: max(current, predicted) / targetValue * currentReplicas
        $effectiveMetric = max($currentValue, $predictedValue);
        $scaleFactor = $policy->targetValue > 0 ? $effectiveMetric / $policy->targetValue : 1.0;

        if ($scaleFactor >= $policy->scaleUpThreshold) {
            $desired = (int)ceil($currentReplicas * $scaleFactor);
            $desired = min($policy->maxReplicas, max($policy->minReplicas, $desired));

            if ($desired > $currentReplicas) {
                $this->lastScaledAt[$policyName] = $now;
                return new ScalingDecision(
                    policyName: $policyName,
                    action: ScalingDecision::ACTION_SCALE_UP,
                    currentReplicas: $currentReplicas,
                    desiredReplicas: $desired,
                    currentMetricValue: $currentValue,
                    predictedMetricValue: $predictedValue,
                    reason: sprintf(
                        'Workload factor %.2f exceeds scale-up threshold %.2f (predicted: %.2f)',
                        $scaleFactor,
                        $policy->scaleUpThreshold,
                        $predictedValue
                    )
                );
            }
        } elseif ($scaleFactor <= $policy->scaleDownThreshold) {
            $desired = (int)floor($currentReplicas * $scaleFactor);
            $desired = max($policy->minReplicas, min($policy->maxReplicas, $desired));

            if ($desired < $currentReplicas) {
                $this->lastScaledAt[$policyName] = $now;
                return new ScalingDecision(
                    policyName: $policyName,
                    action: ScalingDecision::ACTION_SCALE_DOWN,
                    currentReplicas: $currentReplicas,
                    desiredReplicas: $desired,
                    currentMetricValue: $currentValue,
                    predictedMetricValue: $predictedValue,
                    reason: sprintf(
                        'Workload factor %.2f is below scale-down threshold %.2f (predicted: %.2f)',
                        $scaleFactor,
                        $policy->scaleDownThreshold,
                        $predictedValue
                    )
                );
            }
        }

        return new ScalingDecision(
            policyName: $policyName,
            action: ScalingDecision::ACTION_MAINTAIN,
            currentReplicas: $currentReplicas,
            desiredReplicas: $currentReplicas,
            currentMetricValue: $currentValue,
            predictedMetricValue: $predictedValue,
            reason: sprintf('Workload within normal bounds (factor: %.2f, predicted: %.2f)', $scaleFactor, $predictedValue)
        );
    }
}
