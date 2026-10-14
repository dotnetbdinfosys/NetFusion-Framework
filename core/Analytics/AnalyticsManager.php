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

namespace NetFusion\Core\Analytics;

use NetFusion\Core\Analytics\Ingestion\RealtimeIndex;
use NetFusion\Core\Analytics\Models\AnalyticsEvent;
use NetFusion\Core\Analytics\Query\AnalyticsQuery;
use NetFusion\Core\Analytics\Query\QueryExecutor;
use NetFusion\Core\Analytics\Storage\ColumnarSegment;
use NetFusion\Core\Analytics\Storage\SegmentManager;

/**
 * Master Enterprise Distributed Real-Time OLAP Facade & Orchestrator (Apache Druid & ClickHouse Architecture).
 * Coordinates columnar segment storage, real-time streaming ingestion, roll-up pre-aggregation, and scatter-gather queries.
 */
class AnalyticsManager
{
    /** @var array<string, mixed> */
    protected array $config;
    protected SegmentManager $segmentManager;
    protected QueryExecutor $queryExecutor;
    /** @var array<string, RealtimeIndex> [dataSource => RealtimeIndex] */
    protected array $realtimeIndexes = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->segmentManager = new SegmentManager();
        $this->queryExecutor = new QueryExecutor($this->segmentManager);
    }

    public function getSegmentManager(): SegmentManager
    {
        return $this->segmentManager;
    }

    public function getQueryExecutor(): QueryExecutor
    {
        return $this->queryExecutor;
    }

    /**
     * Get or create a RealtimeIndex ingestion buffer for a data source.
     */
    public function realtime(string $dataSource, array $options = []): RealtimeIndex
    {
        $ds = strtolower($dataSource);
        if (!isset($this->realtimeIndexes[$ds])) {
            $mergedOptions = array_merge([
                'maxRowsInMemory' => $this->config['max_rows_in_memory'] ?? 5000,
                'rollup' => $this->config['rollup'] ?? true,
            ], $options);

            $this->realtimeIndexes[$ds] = new RealtimeIndex($ds, $this->segmentManager, $mergedOptions);
        }

        return $this->realtimeIndexes[$ds];
    }

    /**
     * Ingest an event into a data source.
     *
     * @param array<string, mixed> $dimensions
     * @param array<string, float|int> $metrics
     */
    public function ingest(string $dataSource, int $timestamp, array $dimensions = [], array $metrics = []): void
    {
        $event = new AnalyticsEvent($timestamp, $dimensions, $metrics);
        $this->realtime($dataSource)->ingest($event);
    }

    /**
     * Flush all buffered real-time events for a data source into an immutable segment.
     */
    public function flush(string $dataSource): ?ColumnarSegment
    {
        $ds = strtolower($dataSource);
        if (isset($this->realtimeIndexes[$ds])) {
            return $this->realtimeIndexes[$ds]->flush();
        }
        return null;
    }

    /**
     * Initialize a new analytical query builder.
     */
    public function query(string $dataSource): AnalyticsQuery
    {
        return new AnalyticsQuery($dataSource);
    }

    /**
     * Execute an analytical query.
     *
     * @return array<array<string, mixed>>
     */
    public function execute(AnalyticsQuery $query): array
    {
        // Flush any active buffer for this data source before executing to ensure fresh visibility
        $this->flush($query->getDataSource());

        return $this->queryExecutor->execute($query);
    }
}
