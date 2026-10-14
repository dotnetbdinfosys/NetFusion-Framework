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

namespace NetFusion\Core\Data\TimeSeries\Contracts;

use NetFusion\Core\Data\TimeSeries\DataPoint;
use NetFusion\Core\Data\TimeSeries\TimeSeries;

interface TimeSeriesStoreInterface
{
    /**
     * Ingest one or more data points.
     */
    public function insert(DataPoint ...$points): bool;

    /**
     * Query time-series data for a metric in a given time range.
     */
    public function query(
        string $metric,
        float $from,
        float $to,
        array $tags = [],
        ?string $aggregation = null,
        ?int $stepSeconds = null
    ): TimeSeries;

    /**
     * Downsample raw metric points into rollup buckets.
     */
    public function downsample(string $metric, int $windowSeconds, string $aggregation = 'avg'): int;

    /**
     * Prune raw time-series data older than a timestamp.
     */
    public function prune(float $olderThan): int;

    /**
     * Get list of all distinct metric names.
     *
     * @return string[]
     */
    public function getMetrics(): array;

    /**
     * Get total point count.
     */
    public function count(?string $metric = null): int;
}
