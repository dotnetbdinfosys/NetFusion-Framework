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

namespace NetFusion\Core\Telemetry\Metrics;

use NetFusion\Core\Telemetry\Contracts\HistogramInterface;

class Histogram extends AbstractMetric implements HistogramInterface
{
    public const DEFAULT_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    /** @var float[] */
    protected array $buckets;

    /**
     * @var array<string, array{
     *     count: int,
     *     sum: float,
     *     bucket_counts: array<string, int>,
     *     labels: array<string, string>
     * }>
     */
    protected array $series = [];

    /**
     * @param string $name
     * @param string $description
     * @param string $unit
     * @param float[] $buckets
     */
    public function __construct(
        string $name,
        string $description = '',
        string $unit = 's',
        array $buckets = []
    ) {
        parent::__construct($name, $description, $unit);
        $b = empty($buckets) ? self::DEFAULT_BUCKETS : $buckets;
        sort($b);
        $this->buckets = array_values(array_unique(array_map('floatval', $b)));
    }

    public function getType(): string
    {
        return 'histogram';
    }

    public function getBuckets(): array
    {
        return $this->buckets;
    }

    public function record(float|int $value, array $labels = []): self
    {
        $key = $this->getLabelKey($labels);

        if (!isset($this->series[$key])) {
            $initialBuckets = [];
            foreach ($this->buckets as $b) {
                $initialBuckets[(string)$b] = 0;
            }
            $initialBuckets['+Inf'] = 0;

            $this->series[$key] = [
                'count' => 0,
                'sum' => 0.0,
                'bucket_counts' => $initialBuckets,
                'labels' => $this->normalizeLabels($labels),
            ];
        }

        $this->series[$key]['count']++;
        $this->series[$key]['sum'] += (float)$value;

        // Cumulative bucket increments
        foreach ($this->buckets as $b) {
            if ($value <= $b) {
                $this->series[$key]['bucket_counts'][(string)$b]++;
            }
        }
        $this->series[$key]['bucket_counts']['+Inf']++;

        return $this;
    }

    public function time(callable $callback, array $labels = []): mixed
    {
        $start = microtime(true);
        try {
            return $callback();
        } finally {
            $duration = microtime(true) - $start;
            $this->record($duration, $labels);
        }
    }

    public function collect(): array
    {
        $samples = [];

        foreach ($this->series as $item) {
            $baseLabels = $item['labels'];

            // 1. Bucket samples
            foreach ($item['bucket_counts'] as $le => $count) {
                $bucketLabels = array_merge($baseLabels, ['le' => (string)$le]);
                $samples[] = new MetricSample(
                    name: $this->name,
                    value: $count,
                    labels: $bucketLabels,
                    suffix: '_bucket'
                );
            }

            // 2. Sum sample
            $samples[] = new MetricSample(
                name: $this->name,
                value: $item['sum'],
                labels: $baseLabels,
                suffix: '_sum'
            );

            // 3. Count sample
            $samples[] = new MetricSample(
                name: $this->name,
                value: $item['count'],
                labels: $baseLabels,
                suffix: '_count'
            );
        }

        if (empty($samples)) {
            // Default baseline
            foreach ($this->buckets as $b) {
                $samples[] = new MetricSample(
                    name: $this->name,
                    value: 0,
                    labels: ['le' => (string)$b],
                    suffix: '_bucket'
                );
            }
            $samples[] = new MetricSample(name: $this->name, value: 0, labels: ['le' => '+Inf'], suffix: '_bucket');
            $samples[] = new MetricSample(name: $this->name, value: 0, labels: [], suffix: '_sum');
            $samples[] = new MetricSample(name: $this->name, value: 0, labels: [], suffix: '_count');
        }

        return $samples;
    }

    public function reset(): void
    {
        $this->series = [];
    }
}
