<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Telemetry/HealthSignal.php
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

/**
 * Real-time health metric signal emitted by an infrastructure service.
 */
class HealthSignal
{
    public function __construct(
        private string $serviceName,
        private string $metricName,
        private float $value,
        private int $timestamp = 0,
        private array $metadata = []
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = time();
        }
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getMetricName(): string
    {
        return $this->metricName;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'service'   => $this->serviceName,
            'metric'    => $this->metricName,
            'value'     => $this->value,
            'timestamp' => $this->timestamp,
            'metadata'  => $this->metadata,
        ];
    }
}
