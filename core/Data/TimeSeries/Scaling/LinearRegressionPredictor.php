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

use NetFusion\Core\Data\TimeSeries\TimeSeries;

class LinearRegressionPredictor
{
    /**
     * Forecast the metric value at future timestamp (or relative horizon seconds).
     *
     * @return array{predicted_value: float, slope: float, intercept: float, r_squared: float}
     */
    public static function forecast(TimeSeries $series, float $horizonSeconds = 60.0): array
    {
        $points = $series->getPoints();
        $n = count($points);

        if ($n === 0) {
            return ['predicted_value' => 0.0, 'slope' => 0.0, 'intercept' => 0.0, 'r_squared' => 0.0];
        }

        if ($n === 1) {
            return ['predicted_value' => $points[0]->value, 'slope' => 0.0, 'intercept' => $points[0]->value, 'r_squared' => 1.0];
        }

        $baseTs = $points[0]->timestamp;
        $xVals = [];
        $yVals = [];

        foreach ($points as $p) {
            $xVals[] = $p->timestamp - $baseTs;
            $yVals[] = $p->value;
        }

        $sumX = array_sum($xVals);
        $sumY = array_sum($yVals);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        $sumY2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += ($xVals[$i] * $yVals[$i]);
            $sumX2 += ($xVals[$i] ** 2);
            $sumY2 += ($yVals[$i] ** 2);
        }

        $denominator = ($n * $sumX2) - ($sumX ** 2);
        if ($denominator == 0.0) {
            $slope = 0.0;
            $intercept = $sumY / $n;
        } else {
            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
            $intercept = ($sumY - ($slope * $sumX)) / $n;
        }

        // R-squared calculation
        $meanY = $sumY / $n;
        $ssTot = 0.0;
        $ssRes = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $predictedY = ($slope * $xVals[$i]) + $intercept;
            $ssTot += ($yVals[$i] - $meanY) ** 2;
            $ssRes += ($yVals[$i] - $predictedY) ** 2;
        }
        $rSquared = $ssTot > 0 ? max(0.0, 1.0 - ($ssRes / $ssTot)) : 1.0;

        $latestX = end($xVals);
        $targetX = $latestX + $horizonSeconds;
        $predictedValue = max(0.0, ($slope * $targetX) + $intercept);

        return [
            'predicted_value' => round($predictedValue, 3),
            'slope' => round($slope, 5),
            'intercept' => round($intercept, 3),
            'r_squared' => round($rSquared, 3),
        ];
    }
}
