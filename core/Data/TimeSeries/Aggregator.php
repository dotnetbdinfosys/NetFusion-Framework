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

namespace NetFusion\Core\Data\TimeSeries;

class Aggregator
{
    public const AGG_AVG = 'avg';
    public const AGG_SUM = 'sum';
    public const AGG_MIN = 'min';
    public const AGG_MAX = 'max';
    public const AGG_COUNT = 'count';
    public const AGG_STDDEV = 'stddev';
    public const AGG_P50 = 'p50';
    public const AGG_P90 = 'p90';
    public const AGG_P95 = 'p95';
    public const AGG_P99 = 'p99';
    public const AGG_LAST = 'last';
    public const AGG_FIRST = 'first';

    /**
     * @param float[] $values
     */
    public static function aggregate(array $values, string $func): float
    {
        if (empty($values)) {
            return 0.0;
        }

        return match (strtolower($func)) {
            self::AGG_AVG => self::avg($values),
            self::AGG_SUM => self::sum($values),
            self::AGG_MIN => self::min($values),
            self::AGG_MAX => self::max($values),
            self::AGG_COUNT => (float)count($values),
            self::AGG_STDDEV => self::stddev($values),
            self::AGG_P50 => self::percentile($values, 50),
            self::AGG_P90 => self::percentile($values, 90),
            self::AGG_P95 => self::percentile($values, 95),
            self::AGG_P99 => self::percentile($values, 99),
            self::AGG_LAST => (float)end($values),
            self::AGG_FIRST => (float)reset($values),
            default => self::avg($values),
        };
    }

    public static function avg(array $values): float
    {
        return empty($values) ? 0.0 : array_sum($values) / count($values);
    }

    public static function sum(array $values): float
    {
        return empty($values) ? 0.0 : (float)array_sum($values);
    }

    public static function min(array $values): float
    {
        return empty($values) ? 0.0 : (float)min($values);
    }

    public static function max(array $values): float
    {
        return empty($values) ? 0.0 : (float)max($values);
    }

    public static function stddev(array $values): float
    {
        $count = count($values);
        if ($count <= 1) {
            return 0.0;
        }

        $mean = self::avg($values);
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }

        return sqrt($variance / ($count - 1));
    }

    public static function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        if ($count === 1) {
            return (float)$values[0];
        }

        $index = ($percentile / 100.0) * ($count - 1);
        $lower = (int)floor($index);
        $upper = (int)ceil($index);

        if ($lower === $upper) {
            return (float)$values[$lower];
        }

        $weight = $index - $lower;
        return (float)($values[$lower] * (1.0 - $weight) + $values[$upper] * $weight);
    }

    public static function median(array $values): float
    {
        return self::percentile($values, 50.0);
    }

    /**
     * Compute rate of change per second between data points.
     *
     * @param DataPoint[] $points
     * @return DataPoint[]
     */
    public static function rate(array $points): array
    {
        if (count($points) < 2) {
            return [];
        }

        $rates = [];
        for ($i = 1; $i < count($points); $i++) {
            $prev = $points[$i - 1];
            $curr = $points[$i];
            $timeDelta = $curr->timestamp - $prev->timestamp;
            if ($timeDelta > 0) {
                $valDelta = $curr->value - $prev->value;
                $rateVal = $valDelta / $timeDelta;
                $rates[] = new DataPoint($curr->metric . '_rate', $rateVal, $curr->timestamp, $curr->tags);
            }
        }

        return $rates;
    }
}
