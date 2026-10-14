<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Metrics\Instruments;

/**
 * Class Histogram
 *
 * Explicit-bucket distribution metric for latency, payload size, and duration tracking with quantile estimation.
 */
class Histogram extends AbstractInstrument
{
    protected string $type = 'histogram';
    /** @var array<int, float> */
    protected array $boundaries;
    /** @var array<string, array> */
    protected array $series = [];

    public function __construct(
        string $name,
        string $description = '',
        string $unit = 'ms',
        array $boundaries = [5.0, 10.0, 25.0, 50.0, 100.0, 250.0, 500.0, 1000.0, 2500.0, 5000.0, 10000.0]
    ) {
        parent::__construct($name, $description, $unit);
        sort($boundaries);
        $this->boundaries = array_values(array_unique($boundaries));
    }

    public function getBoundaries(): array
    {
        return $this->boundaries;
    }

    public function record(float|int $value, array $attributes = []): void
    {
        $val = (float) $value;
        $key = $this->attributeKey($attributes);

        if (!isset($this->series[$key])) {
            $bucketCounts = array_fill(0, count($this->boundaries) + 1, 0);
            $this->series[$key] = [
                'attributes' => $attributes,
                'count' => 0,
                'sum' => 0.0,
                'min' => $val,
                'max' => $val,
                'bucket_counts' => $bucketCounts,
                'samples' => [],
                'last_updated' => microtime(true),
            ];
        }

        $s = &$this->series[$key];
        $s['count']++;
        $s['sum'] += $val;
        $s['min'] = min($s['min'], $val);
        $s['max'] = max($s['max'], $val);
        $s['last_updated'] = microtime(true);

        // Retain rolling window of samples for quantile calculations (bounded to 1000)
        if (count($s['samples']) < 1000) {
            $s['samples'][] = $val;
        } else {
            $replaceIdx = mt_rand(0, 999);
            $s['samples'][$replaceIdx] = $val;
        }

        // Increment appropriate bucket
        $placed = false;
        foreach ($this->boundaries as $idx => $boundary) {
            if ($val <= $boundary) {
                $s['bucket_counts'][$idx]++;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            // Count in +Inf bucket
            $s['bucket_counts'][count($this->boundaries)]++;
        }
    }

    public function getPercentile(float $percentile, array $attributes = []): float
    {
        $key = $this->attributeKey($attributes);
        if (!isset($this->series[$key]) || empty($this->series[$key]['samples'])) {
            return 0.0;
        }

        $samples = $this->series[$key]['samples'];
        sort($samples);
        $count = count($samples);

        $rank = ($percentile / 100.0) * ($count - 1);
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);

        if ($lower === $upper) {
            return round($samples[$lower], 3);
        }

        $weight = $rank - $lower;
        return round($samples[$lower] + $weight * ($samples[$upper] - $samples[$lower]), 3);
    }

    public function getData(): array
    {
        $points = [];
        foreach ($this->series as $s) {
            $points[] = [
                'attributes' => $s['attributes'],
                'count' => $s['count'],
                'sum' => round($s['sum'], 4),
                'min' => $s['min'],
                'max' => $s['max'],
                'boundaries' => $this->boundaries,
                'bucket_counts' => $s['bucket_counts'],
                'p50' => $this->computeQuantile($s['samples'], 50),
                'p90' => $this->computeQuantile($s['samples'], 90),
                'p95' => $this->computeQuantile($s['samples'], 95),
                'p99' => $this->computeQuantile($s['samples'], 99),
                'last_updated' => $s['last_updated'],
            ];
        }

        return [
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'type' => $this->type,
            'points' => $points,
        ];
    }

    protected function computeQuantile(array $samples, float $percentile): float
    {
        if (empty($samples)) {
            return 0.0;
        }
        sort($samples);
        $count = count($samples);
        $index = (int) floor(($percentile / 100.0) * ($count - 1));
        return round((float) $samples[$index], 3);
    }

    public function reset(): void
    {
        $this->series = [];
    }
}
