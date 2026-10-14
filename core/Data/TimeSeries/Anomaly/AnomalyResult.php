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

namespace NetFusion\Core\Data\TimeSeries\Anomaly;

use JsonSerializable;

class AnomalyResult implements JsonSerializable
{
    /**
     * @param array<int, array{timestamp: float, value: float, score: float, expected: float, message: string}> $anomalies
     */
    public function __construct(
        public readonly bool $isAnomaly,
        public readonly float $score,
        public readonly float $expectedValue,
        public readonly float $actualValue,
        public readonly float $threshold,
        public readonly string $method,
        public readonly array $anomalies = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'is_anomaly' => $this->isAnomaly,
            'score' => $this->score,
            'expected_value' => $this->expectedValue,
            'actual_value' => $this->actualValue,
            'threshold' => $this->threshold,
            'method' => $this->method,
            'anomalies_count' => count($this->anomalies),
            'anomalies' => $this->anomalies,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
