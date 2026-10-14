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

class ScalingDecision implements JsonSerializable
{
    public const ACTION_SCALE_UP = 'SCALE_UP';
    public const ACTION_SCALE_DOWN = 'SCALE_DOWN';
    public const ACTION_MAINTAIN = 'MAINTAIN';
    public const ACTION_COOLDOWN = 'COOLDOWN';

    public function __construct(
        public readonly string $policyName,
        public readonly string $action,
        public readonly int $currentReplicas,
        public readonly int $desiredReplicas,
        public readonly float $currentMetricValue,
        public readonly float $predictedMetricValue,
        public readonly string $reason
    ) {
    }

    public function toArray(): array
    {
        return [
            'policy_name' => $this->policyName,
            'action' => $this->action,
            'current_replicas' => $this->currentReplicas,
            'desired_replicas' => $this->desiredReplicas,
            'current_metric_value' => $this->currentMetricValue,
            'predicted_metric_value' => $this->predictedMetricValue,
            'reason' => $this->reason,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
