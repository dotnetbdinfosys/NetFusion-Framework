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

class IqrDetector implements AnomalyDetectorInterface
{
    public function __construct(
        protected float $defaultMultiplier = 1.5
    ) {
    }

    public function getName(): string
    {
        return 'iqr';
    }

    public function detect(TimeSeries $series, array $options = []): AnomalyResult
    {
        $multiplier = (float)($options['multiplier'] ?? $this->defaultMultiplier);
        $points = $series->getPoints();
        $count = count($points);

        if ($count < 4) {
            return new AnomalyResult(
                isAnomaly: false,
                score: 0.0,
                expectedValue: $series->latest()?->value ?? 0.0,
                actualValue: $series->latest()?->value ?? 0.0,
                threshold: $multiplier,
                method: $this->getName(),
                anomalies: []
            );
        }

        $values = $series->getValues();
        $q1 = Aggregator::percentile($values, 25.0);
        $q3 = Aggregator::percentile($values, 75.0);
        $iqr = $q3 - $q1;
        $median = Aggregator::median($values);

        $lowerBound = $q1 - ($multiplier * $iqr);
        $upperBound = $q3 + ($multiplier * $iqr);

        $anomalies = [];
        $latestPoint = end($points);

        foreach ($points as $p) {
            if ($p->value < $lowerBound || $p->value > $upperBound) {
                $deviation = $iqr > 0 ? abs($p->value - $median) / $iqr : 0.0;
                $anomalies[] = [
                    'timestamp' => $p->timestamp,
                    'value' => $p->value,
                    'score' => round($deviation, 3),
                    'expected' => round($median, 3),
                    'message' => sprintf('Value %.2f outside IQR bounds [%.2f, %.2f]', $p->value, $lowerBound, $upperBound),
                ];
            }
        }

        $latestVal = $latestPoint->value;
        $isLatestAnomaly = $latestVal < $lowerBound || $latestVal > $upperBound;
        $latestScore = $iqr > 0 ? abs($latestVal - $median) / $iqr : 0.0;

        return new AnomalyResult(
            isAnomaly: !empty($anomalies) || $isLatestAnomaly,
            score: round($latestScore, 3),
            expectedValue: round($median, 3),
            actualValue: $latestVal,
            threshold: $multiplier,
            method: $this->getName(),
            anomalies: $anomalies
        );
    }
}
