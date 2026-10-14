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

class EwmaDetector implements AnomalyDetectorInterface
{
    public function __construct(
        protected float $defaultAlpha = 0.3,
        protected float $defaultThreshold = 3.0
    ) {
    }

    public function getName(): string
    {
        return 'ewma';
    }

    public function detect(TimeSeries $series, array $options = []): AnomalyResult
    {
        $alpha = (float)($options['alpha'] ?? $this->defaultAlpha);
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

        $s = $points[0]->value;
        $residuals = [];
        $expectedSeries = [];

        foreach ($points as $p) {
            $expectedSeries[] = $s;
            $residuals[] = abs($p->value - $s);
            $s = ($alpha * $p->value) + ((1.0 - $alpha) * $s);
        }

        $residualStddev = Aggregator::stddev($residuals);
        $anomalies = [];
        $latestPoint = end($points);
        $latestIdx = $count - 1;
        $latestResidual = $residuals[$latestIdx];
        $latestScore = $residualStddev > 0 ? $latestResidual / $residualStddev : 0.0;

        foreach ($points as $idx => $p) {
            $expected = $expectedSeries[$idx];
            $res = $residuals[$idx];
            $score = $residualStddev > 0 ? $res / $residualStddev : 0.0;

            if ($score >= $threshold) {
                $anomalies[] = [
                    'timestamp' => $p->timestamp,
                    'value' => $p->value,
                    'score' => round($score, 3),
                    'expected' => round($expected, 3),
                    'message' => sprintf('Value %.2f diverges from dynamic EWMA baseline %.2f (score=%.2f)', $p->value, $expected, $score),
                ];
            }
        }

        $isLatestAnomaly = $latestScore >= $threshold;

        return new AnomalyResult(
            isAnomaly: !empty($anomalies) || $isLatestAnomaly,
            score: round($latestScore, 3),
            expectedValue: round($expectedSeries[$latestIdx], 3),
            actualValue: $latestPoint->value,
            threshold: $threshold,
            method: $this->getName(),
            anomalies: $anomalies
        );
    }
}
