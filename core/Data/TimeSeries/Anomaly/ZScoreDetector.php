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

use NetFusion\Core\Data\TimeSeries\Aggregator;
use NetFusion\Core\Data\TimeSeries\Anomaly\Contracts\AnomalyDetectorInterface;
use NetFusion\Core\Data\TimeSeries\TimeSeries;

class ZScoreDetector implements AnomalyDetectorInterface
{
    public function __construct(
        protected float $defaultThreshold = 3.0
    ) {
    }

    public function getName(): string
    {
        return 'zscore';
    }

    public function detect(TimeSeries $series, array $options = []): AnomalyResult
    {
        $threshold = (float)($options['threshold'] ?? $this->defaultThreshold);
        $points = $series->getPoints();
        $count = count($points);

        if ($count < 3) {
            return new AnomalyResult(
                isAnomaly: false,
                score: 0.0,
                expectedValue: $series->latest()?->value ?? 0.0,
                actualValue: $series->latest()?->value ?? 0.0,
                threshold: $threshold,
                method: $this->getName(),
                anomalies: []
            );
        }

        $values = $series->getValues();
        $mean = Aggregator::avg($values);
        $stddev = Aggregator::stddev($values);

        $anomalies = [];
        $maxZ = 0.0;
        $latestPoint = end($points);
        $latestZ = $stddev > 0 ? abs($latestPoint->value - $mean) / $stddev : 0.0;

        foreach ($points as $p) {
            $z = $stddev > 0 ? abs($p->value - $mean) / $stddev : 0.0;
            if ($z > $maxZ) {
                $maxZ = $z;
            }

            if ($z >= $threshold) {
                $anomalies[] = [
                    'timestamp' => $p->timestamp,
                    'value' => $p->value,
                    'score' => round($z, 3),
                    'expected' => round($mean, 3),
                    'message' => sprintf('Value %.2f exceeds Z-Score threshold (z=%.2f >= %.2f)', $p->value, $z, $threshold),
                ];
            }
        }

        $isLatestAnomaly = $latestZ >= $threshold;

        return new AnomalyResult(
            isAnomaly: !empty($anomalies) || $isLatestAnomaly,
            score: round($latestZ, 3),
            expectedValue: round($mean, 3),
            actualValue: $latestPoint->value,
            threshold: $threshold,
            method: $this->getName(),
            anomalies: $anomalies
        );
    }
}
