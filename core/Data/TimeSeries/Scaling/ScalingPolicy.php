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

use JsonSerializable;

class ScalingPolicy implements JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $metric,
        public readonly float $targetValue,
        public readonly int $minReplicas = 1,
        public readonly int $maxReplicas = 10,
        public readonly float $scaleUpThreshold = 1.2,
        public readonly float $scaleDownThreshold = 0.7,
        public readonly int $cooldownSeconds = 300,
        public readonly int $forecastHorizonSeconds = 60
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'metric' => $this->metric,
            'target_value' => $this->targetValue,
            'min_replicas' => $this->minReplicas,
            'max_replicas' => $this->maxReplicas,
            'scale_up_threshold' => $this->scaleUpThreshold,
            'scale_down_threshold' => $this->scaleDownThreshold,
            'cooldown_seconds' => $this->cooldownSeconds,
            'forecast_horizon_seconds' => $this->forecastHorizonSeconds,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
