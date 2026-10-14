<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Analysis/HypothesisRanker.php
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

namespace NetFusion\Core\Infrastructure\SelfHealing\Analysis;

use NetFusion\Core\Infrastructure\SelfHealing\Enums\ActionType;
use NetFusion\Core\Infrastructure\SelfHealing\Telemetry\AnomalyEvent;

/**
 * Probabilistic Root-Cause Hypothesis Ranker using symptom weighting.
 */
class HypothesisRanker
{
    /**
     * Rank potential failure hypotheses given anomaly events and recommend an action.
     *
     * @param AnomalyEvent[] $anomalies
     * @return array{
     *   hypotheses: array<string, float>,
     *   primary_hypothesis: string,
     *   confidence: float,
     *   recommended_action: ActionType
     * }
     */
    public function rank(array $anomalies): array
    {
        if (empty($anomalies)) {
            return [
                'hypotheses'         => ['HEALTHY' => 1.0],
                'primary_hypothesis' => 'HEALTHY',
                'confidence'         => 1.0,
                'recommended_action' => ActionType::SCALE_OUT,
            ];
        }

        $scores = [
            'MEMORY_LEAK'               => 0.1,
            'TRAFFIC_SPIKE_SATURATION'  => 0.1,
            'DATABASE_BOTTLENECK'       => 0.1,
            'BAD_DEPLOYMENT_REGRESSION' => 0.1,
            'NETWORK_CONGESTION'        => 0.1,
        ];

        foreach ($anomalies as $event) {
            $metric = strtolower($event->getMetricName());
            $severityWeight = $event->getSeverity()->priority() * $event->getZScore();

            if (str_contains($metric, 'mem') || str_contains($metric, 'heap') || str_contains($metric, 'rss')) {
                $scores['MEMORY_LEAK'] += 3.5 * $severityWeight;
            }

            if (str_contains($metric, 'cpu') || str_contains($metric, 'rps') || str_contains($metric, 'throughput')) {
                $scores['TRAFFIC_SPIKE_SATURATION'] += 3.0 * $severityWeight;
            }

            if (str_contains($metric, 'db') || str_contains($metric, 'query') || str_contains($metric, 'pool') || str_contains($metric, 'sql')) {
                $scores['DATABASE_BOTTLENECK'] += 3.8 * $severityWeight;
            }

            if (str_contains($metric, '5xx') || str_contains($metric, 'error') || str_contains($metric, 'crash')) {
                $scores['BAD_DEPLOYMENT_REGRESSION'] += 4.0 * $severityWeight;
            }

            if (str_contains($metric, 'latency') || str_contains($metric, 'rtt') || str_contains($metric, 'drop') || str_contains($metric, 'timeout')) {
                $scores['NETWORK_CONGESTION'] += 2.5 * $severityWeight;
            }
        }

        // Normalize scores to probabilities summing to 1.0
        $totalScore = array_sum($scores);
        $normalized = [];
        foreach ($scores as $hypothesis => $rawScore) {
            $normalized[$hypothesis] = round($rawScore / $totalScore, 4);
        }

        arsort($normalized);
        $primaryHypothesis = (string)array_key_first($normalized);
        $confidence = $normalized[$primaryHypothesis];

        $recommendedAction = match ($primaryHypothesis) {
            'MEMORY_LEAK'               => ActionType::RESTART_POD,
            'TRAFFIC_SPIKE_SATURATION'  => ActionType::SCALE_OUT,
            'DATABASE_BOTTLENECK'       => ActionType::CACHE_FLUSH,
            'BAD_DEPLOYMENT_REGRESSION' => ActionType::CONFIG_ROLLBACK,
            'NETWORK_CONGESTION'        => ActionType::TRAFFIC_REROUTE,
            default                     => ActionType::SCALE_OUT,
        };

        return [
            'hypotheses'         => $normalized,
            'primary_hypothesis' => $primaryHypothesis,
            'confidence'         => $confidence,
            'recommended_action' => $recommendedAction,
        ];
    }
}
