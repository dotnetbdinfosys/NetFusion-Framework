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

use NetFusion\Core\Data\TimeSeries\Anomaly\AnomalyDetector;
use NetFusion\Core\Data\TimeSeries\Contracts\TimeSeriesStoreInterface;
use NetFusion\Core\Data\TimeSeries\Scaling\AutoScaler;
use NetFusion\Core\Data\TimeSeries\Store\ArrayTimeSeriesStore;
use NetFusion\Core\Data\TimeSeries\Store\DatabaseTimeSeriesStore;

class TimeSeriesManager
{
    protected TimeSeriesStoreInterface $store;
    protected AnomalyDetector $detector;
    protected AutoScaler $scaler;

    public function __construct(
        ?TimeSeriesStoreInterface $store = null,
        ?AnomalyDetector $detector = null,
        ?AutoScaler $scaler = null
    ) {
        $this->store = $store ?? $this->resolveDefaultStore();
        $this->detector = $detector ?? new AnomalyDetector();
        $this->scaler = $scaler ?? new AutoScaler();
    }

    public function getStore(): TimeSeriesStoreInterface
    {
        return $this->store;
    }

    public function setStore(TimeSeriesStoreInterface $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function detector(): AnomalyDetector
    {
        return $this->detector;
    }

    public function scaler(): AutoScaler
    {
        return $this->scaler;
    }

    /**
     * Ingest a single time-series metric data point.
     */
    public function write(string $metric, float $value, array $tags = [], ?float $timestamp = null): bool
    {
        $point = DataPoint::create($metric, $value, $timestamp, $tags);
        return $this->store->insert($point);
    }

    /**
     * Ingest a batch of DataPoint objects.
     *
     * @param DataPoint[] $points
     */
    public function writeBatch(array $points): bool
    {
        return $this->store->insert(...$points);
    }

    /**
     * Query time-series metric series over a time range.
     */
    public function query(
        string $metric,
        float $from,
        float $to,
        array $tags = [],
        ?string $aggregation = null,
        ?int $stepSeconds = null
    ): TimeSeries {
        return $this->store->query($metric, $from, $to, $tags, $aggregation, $stepSeconds);
    }

    /**
     * Downsample raw metric points into rollups.
     */
    public function downsample(string $metric, int $windowSeconds, string $aggregation = 'avg'): int
    {
        return $this->store->downsample($metric, $windowSeconds, $aggregation);
    }

    /**
     * Prune raw time-series data older than a timestamp.
     */
    public function prune(float $olderThan): int
    {
        return $this->store->prune($olderThan);
    }

    protected function resolveDefaultStore(): TimeSeriesStoreInterface
    {
        if (function_exists('app') && app()->has('db')) {
            try {
                return new DatabaseTimeSeriesStore(app('db'));
            } catch (\Throwable) {
                // Fallback to array store
            }
        }

        return new ArrayTimeSeriesStore();
    }
}
