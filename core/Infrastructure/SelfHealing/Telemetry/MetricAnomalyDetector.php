<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Telemetry/MetricAnomalyDetector.php
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

namespace NetFusion\Core\Infrastructure\SelfHealing\Telemetry;

use NetFusion\Core\Infrastructure\SelfHealing\Enums\IncidentSeverity;

/**
 * Statistical Anomaly Detector utilizing rolling Z-score and 3-sigma thresholds.
 */
class MetricAnomalyDetector
{
    /** @var array<string, array<float>> Service:Metric sliding windows */
    private array $history = [];

    public function __construct(
        private int $windowSize = 50,
        private float $zThreshold = 3.0,
        private int $minSamples = 5
    ) {
    }

    /**
     * Record a new telemetry metric value and check if an anomaly is triggered.
     */
    public function record(string $service, string $metric, float $value, ?int $timestamp = null): ?AnomalyEvent
    {
        $key = "{$service}:{$metric}";
        if (!isset($this->history[$key])) {
            $this->history[$key] = [];
        }

        $count = count($this->history[$key]);

        if ($count < $this->minSamples) {
            $this->history[$key][] = $value;
            return null;
        }

        // Calculate baseline mean and standard deviation from historical samples
        $mean = array_sum($this->history[$key]) / $count;
        $variance = 0.0;
        foreach ($this->history[$key] as $x) {
            $variance += ($x - $mean) ** 2;
        }
        $stdDev = sqrt($variance / $count);

        // Always append new value and keep window size
        $this->history[$key][] = $value;
        if (count($this->history[$key]) > $this->windowSize) {
            array_shift($this->history[$key]);
        }

        // Avoid division by zero when variance is near zero
        if ($stdDev < 1e-4) {
            if (abs($value - $mean) > 1.0) {
                // Obvious step jump from constant baseline
                $zScore = abs($value - $mean) * 10.0;
            } else {
                return null;
            }
        } else {
            $zScore = abs($value - $mean) / $stdDev;
        }

        if ($zScore >= $this->zThreshold) {
            $severity = match (true) {
                $zScore >= 4.5 => IncidentSeverity::CRITICAL,
                $zScore >= 3.5 => IncidentSeverity::HIGH,
                $zScore >= 3.0 => IncidentSeverity::MEDIUM,
                default        => IncidentSeverity::LOW,
            };

            $eventId = 'anom_' . substr(md5($key . microtime(true)), 0, 12);
            return new AnomalyEvent(
                $eventId,
                $service,
                $metric,
                $value,
                round($mean, 4),
                round($stdDev, 4),
                round($zScore, 3),
                $severity,
                $timestamp ?? time()
            );
        }

        return null;
    }

    /**
     * Batch ingest multiple health signals and return detected anomaly events.
     *
     * @param HealthSignal[] $signals
     * @return AnomalyEvent[]
     */
    public function detect(array $signals): array
    {
        $events = [];
        foreach ($signals as $signal) {
            $event = $this->record(
                $signal->getServiceName(),
                $signal->getMetricName(),
                $signal->getValue(),
                $signal->getTimestamp()
            );
            if ($event !== null) {
                $events[] = $event;
            }
        }
        return $events;
    }

    /**
     * Retrieve current baseline metrics for a service.
     *
     * @return array{mean: float, std_dev: float, samples: int}
     */
    public function getBaselines(string $service, string $metric): array
    {
        $key = "{$service}:{$metric}";
        $samples = $this->history[$key] ?? [];
        $count = count($samples);

        if ($count === 0) {
            return ['mean' => 0.0, 'std_dev' => 0.0, 'samples' => 0];
        }

        $mean = array_sum($samples) / $count;
        $variance = 0.0;
        foreach ($samples as $x) {
            $variance += ($x - $mean) ** 2;
        }
        $stdDev = sqrt($variance / $count);

        return [
            'mean'    => round($mean, 4),
            'std_dev' => round($stdDev, 4),
            'samples' => $count,
        ];
    }

    /**
     * Clear telemetry historical sliding windows.
     */
    public function reset(): void
    {
        $this->history = [];
    }
}
