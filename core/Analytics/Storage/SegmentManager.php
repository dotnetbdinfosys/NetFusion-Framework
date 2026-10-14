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

namespace NetFusion\Core\Analytics\Storage;

/**
 * Manages segment lifecycle, metadata catalog, and historical segment persistence.
 */
class SegmentManager
{
    /** @var array<string, array<string, ColumnarSegment>> [dataSource => [segmentId => ColumnarSegment]] */
    protected array $segments = [];

    /**
     * Add a completed segment into the catalog.
     */
    public function addSegment(ColumnarSegment $segment): self
    {
        $ds = strtolower($segment->getDataSource());
        if (!isset($this->segments[$ds])) {
            $this->segments[$ds] = [];
        }
        $this->segments[$ds][$segment->getId()] = $segment;
        return $this;
    }

    /**
     * Get a specific segment by data source and segment ID.
     */
    public function getSegment(string $dataSource, string $segmentId): ?ColumnarSegment
    {
        $ds = strtolower($dataSource);
        return $this->segments[$ds][$segmentId] ?? null;
    }

    /**
     * Get all segments matching a time interval [start, end].
     *
     * @return array<ColumnarSegment>
     */
    public function getSegmentsForInterval(string $dataSource, int $start, int $end): array
    {
        $ds = strtolower($dataSource);
        if (!isset($this->segments[$ds])) {
            return [];
        }

        $matched = [];
        foreach ($this->segments[$ds] as $seg) {
            // Overlap condition: seg.start <= query.end AND seg.end >= query.start
            if ($seg->getStartTime() <= $end && $seg->getEndTime() >= $start) {
                $matched[] = $seg;
            }
        }

        return $matched;
    }

    /**
     * List all data sources in the catalog.
     *
     * @return array<string>
     */
    public function listDataSources(): array
    {
        return array_keys($this->segments);
    }

    /**
     * Get total row count across all segments in a data source.
     */
    public function getTotalRowCount(string $dataSource): int
    {
        $ds = strtolower($dataSource);
        if (!isset($this->segments[$ds])) {
            return 0;
        }

        $total = 0;
        foreach ($this->segments[$ds] as $seg) {
            $total += $seg->getRowCount();
        }
        return $total;
    }

    /**
     * Clear all segments from catalog.
     */
    public function clear(): self
    {
        $this->segments = [];
        return $this;
    }
}
