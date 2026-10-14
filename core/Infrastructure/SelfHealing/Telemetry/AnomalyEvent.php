<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Telemetry/AnomalyEvent.php
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
 * Anomaly event triggered when a health signal deviates beyond statistical thresholds.
 */
class AnomalyEvent
{
    public function __construct(
        private string $id,
        private string $serviceName,
        private string $metricName,
        private float $currentValue,
        private float $baselineMean,
        private float $baselineStdDev,
        private float $zScore,
        private IncidentSeverity $severity,
        private int $timestamp = 0
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = time();
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getMetricName(): string
    {
        return $this->metricName;
    }

    public function getCurrentValue(): float
    {
        return $this->currentValue;
    }

    public function getBaselineMean(): float
    {
        return $this->baselineMean;
    }

    public function getBaselineStdDev(): float
    {
        return $this->baselineStdDev;
    }

    public function getZScore(): float
    {
        return $this->zScore;
    }

    public function getSeverity(): IncidentSeverity
    {
        return $this->severity;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getDeviationPercentage(): float
    {
        if (abs($this->baselineMean) < 1e-6) {
            return 100.0;
        }
        return round((($this->currentValue - $this->baselineMean) / $this->baselineMean) * 100.0, 2);
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'service'         => $this->serviceName,
            'metric'          => $this->metricName,
            'current_value'   => $this->currentValue,
            'baseline_mean'   => $this->baselineMean,
            'baseline_std_dev'=> $this->baselineStdDev,
            'z_score'         => $this->zScore,
            'severity'        => $this->severity->value,
            'deviation_pct'   => $this->getDeviationPercentage(),
            'timestamp'       => $this->timestamp,
        ];
    }
}
