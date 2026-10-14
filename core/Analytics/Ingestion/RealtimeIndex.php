<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Analytics\Ingestion;

use NetFusion\Core\Analytics\Enums\AggregationType;
use NetFusion\Core\Analytics\Enums\Granularity;
use NetFusion\Core\Analytics\Models\AnalyticsEvent;
use NetFusion\Core\Analytics\Models\HyperLogLog;
use NetFusion\Core\Analytics\Storage\ColumnarSegment;
use NetFusion\Core\Analytics\Storage\SegmentManager;

/**
 * Real-Time Stream Ingestion & Roll-up Engine.
 * Accumulates streaming events into an in-memory mutable index with optional roll-up pre-aggregation.
 * Automatically flushes to an immutable ColumnarSegment when maxRowsInMemory or segmentInterval is reached.
 */
class RealtimeIndex
{
    protected string $dataSource;
    protected SegmentManager $segmentManager;
    protected int $maxRowsInMemory;
    protected Granularity $queryGranularity;
    protected bool $rollup;
    /** @var array<string, AggregationType> [metricName => AggregationType] */
    protected array $metricAggregations;

    /** @var array<string, array{__time: int, dims: array<string, string>, metrics: array<string, mixed>}> */
    protected array $inMemoryRows = [];
    protected int $currentStartTime = 0;
    protected int $currentEndTime = 0;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $dataSource, SegmentManager $segmentManager, array $options = [])
    {
        $this->dataSource = $dataSource;
        $this->segmentManager = $segmentManager;
        $this->maxRowsInMemory = (int)($options['maxRowsInMemory'] ?? 5000);
        $this->queryGranularity = $options['granularity'] ?? Granularity::MINUTE;
        $this->rollup = (bool)($options['rollup'] ?? true);
        $this->metricAggregations = (array)($options['aggregations'] ?? []);
    }

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    public function getInMemoryRowCount(): int
    {
        return count($this->inMemoryRows);
    }

    /**
     * Ingest an event into memory.
     */
    public function ingest(AnalyticsEvent $event): void
    {
        $rawTs = $event->getTimestamp();
        $bucketedTs = $this->queryGranularity->bucket($rawTs);

        if ($this->currentStartTime === 0 || $bucketedTs < $this->currentStartTime) {
            $this->currentStartTime = $bucketedTs;
        }
        if ($bucketedTs > $this->currentEndTime) {
            $this->currentEndTime = $bucketedTs;
        }

        $dims = $event->getDimensions();
        ksort($dims);

        // Pre-aggregation Roll-up key: bucketed timestamp + sorted dimensions
        if ($this->rollup) {
            $dimKey = $bucketedTs . '|' . json_encode($dims);

            if (!isset($this->inMemoryRows[$dimKey])) {
                $initialMetrics = [];
                foreach ($event->getMetrics() as $m => $val) {
                    $aggType = $this->metricAggregations[$m] ?? AggregationType::SUM;
                    if ($aggType === AggregationType::HLL_UNIQUE) {
                        $hll = new HyperLogLog();
                        $hll->add((string)$val);
                        $initialMetrics[$m] = $hll;
                    } elseif ($aggType === AggregationType::COUNT) {
                        $initialMetrics[$m] = 1.0;
                    } else {
                        $initialMetrics[$m] = (float)$val;
                    }
                }

                $this->inMemoryRows[$dimKey] = [
                    '__time' => $bucketedTs,
                    'dims' => $dims,
                    'metrics' => $initialMetrics,
                ];
            } else {
                // Combine into existing roll-up bucket
                foreach ($event->getMetrics() as $m => $val) {
                    $aggType = $this->metricAggregations[$m] ?? AggregationType::SUM;

                    if ($aggType === AggregationType::HLL_UNIQUE) {
                        if (isset($this->inMemoryRows[$dimKey]['metrics'][$m]) && $this->inMemoryRows[$dimKey]['metrics'][$m] instanceof HyperLogLog) {
                            $this->inMemoryRows[$dimKey]['metrics'][$m]->add((string)$val);
                        }
                    } elseif ($aggType === AggregationType::COUNT) {
                        $this->inMemoryRows[$dimKey]['metrics'][$m] = ($this->inMemoryRows[$dimKey]['metrics'][$m] ?? 0) + 1.0;
                    } elseif ($aggType === AggregationType::MAX) {
                        $this->inMemoryRows[$dimKey]['metrics'][$m] = max($this->inMemoryRows[$dimKey]['metrics'][$m] ?? -INF, (float)$val);
                    } elseif ($aggType === AggregationType::MIN) {
                        $this->inMemoryRows[$dimKey]['metrics'][$m] = min($this->inMemoryRows[$dimKey]['metrics'][$m] ?? INF, (float)$val);
                    } else {
                        // SUM or AVG numerator
                        $this->inMemoryRows[$dimKey]['metrics'][$m] = ($this->inMemoryRows[$dimKey]['metrics'][$m] ?? 0.0) + (float)$val;
                    }
                }
            }
        } else {
            // No rollup: append directly
            $rowKey = count($this->inMemoryRows);
            $this->inMemoryRows[(string)$rowKey] = [
                '__time' => $rawTs,
                'dims' => $dims,
                'metrics' => $event->getMetrics(),
            ];
        }

        if (count($this->inMemoryRows) >= $this->maxRowsInMemory) {
            $this->flush();
        }
    }

    /**
     * Flush in-memory accumulated rows into an immutable ColumnarSegment.
     */
    public function flush(): ?ColumnarSegment
    {
        if (empty($this->inMemoryRows)) {
            return null;
        }

        $segment = new ColumnarSegment(
            $this->dataSource,
            $this->currentStartTime,
            max($this->currentEndTime, $this->currentStartTime + 60)
        );

        foreach ($this->inMemoryRows as $row) {
            $numericMetrics = [];
            foreach ($row['metrics'] as $m => $val) {
                if ($val instanceof HyperLogLog) {
                    $numericMetrics[$m] = (float)$val->cardinality();
                } else {
                    $numericMetrics[$m] = (float)$val;
                }
            }

            $event = new AnalyticsEvent($row['__time'], $row['dims'], $numericMetrics);
            $segment->append($event);
        }

        $this->segmentManager->addSegment($segment);

        // Reset buffer
        $this->inMemoryRows = [];
        $this->currentStartTime = 0;
        $this->currentEndTime = 0;

        return $segment;
    }
}
