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

use NetFusion\Core\Analytics\Models\AnalyticsEvent;

/**
 * Immutable Columnar Segment File Representation (Apache Druid & ClickHouse Segment Architecture).
 * Holds:
 * - Timestamps (__time array)
 * - Dimension Dictionary: [dimName => [val1 => 0, val2 => 1]]
 * - Inverted Bitmap Indexes: [dimName => [valId => BitmapIndex]]
 * - Metric Columns: [metricName => [rowId => float]]
 */
class ColumnarSegment
{
    protected string $id;
    protected string $dataSource;
    protected int $startTime;
    protected int $endTime;
    protected int $rowCount = 0;

    /** @var array<int, int> [rowId => timestamp] */
    protected array $timestamps = [];

    /** @var array<string, array<int, string>> [dimName => [dictId => value]] */
    protected array $dictionaries = [];

    /** @var array<string, array<string, int>> [dimName => [value => dictId]] */
    protected array $reverseDictionaries = [];

    /** @var array<string, array<int, int>> [dimName => [rowId => dictId]] */
    protected array $dimensionColumns = [];

    /** @var array<string, array<int, BitmapIndex>> [dimName => [dictId => BitmapIndex]] */
    protected array $bitmapIndexes = [];

    /** @var array<string, array<int, float>> [metricName => [rowId => floatValue]] */
    protected array $metricColumns = [];

    public function __construct(string $dataSource, int $startTime, int $endTime, string $id = '')
    {
        $this->dataSource = $dataSource;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->id = $id !== '' ? $id : sprintf('%s_%d_%d_%s', $dataSource, $startTime, $endTime, substr(md5(random_bytes(6)), 0, 8));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function getEndTime(): int
    {
        return $this->endTime;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * Ingest an analytical event into this segment.
     */
    public function append(AnalyticsEvent $event): int
    {
        $rowId = $this->rowCount;
        $this->timestamps[$rowId] = $event->getTimestamp();

        // 1. Process Dimensions
        foreach ($event->getDimensions() as $dim => $val) {
            if (!isset($this->reverseDictionaries[$dim])) {
                $this->dictionaries[$dim] = [];
                $this->reverseDictionaries[$dim] = [];
                $this->bitmapIndexes[$dim] = [];
                $this->dimensionColumns[$dim] = [];
            }

            if (!isset($this->reverseDictionaries[$dim][$val])) {
                $dictId = count($this->dictionaries[$dim]);
                $this->dictionaries[$dim][$dictId] = $val;
                $this->reverseDictionaries[$dim][$val] = $dictId;
                $this->bitmapIndexes[$dim][$dictId] = new BitmapIndex();
            } else {
                $dictId = $this->reverseDictionaries[$dim][$val];
            }

            $this->dimensionColumns[$dim][$rowId] = $dictId;
            $this->bitmapIndexes[$dim][$dictId]->set($rowId);
        }

        // 2. Process Metrics
        foreach ($event->getMetrics() as $metric => $numVal) {
            if (!isset($this->metricColumns[$metric])) {
                $this->metricColumns[$metric] = [];
            }
            $this->metricColumns[$metric][$rowId] = (float)$numVal;
        }

        $this->rowCount++;
        return $rowId;
    }

    /**
     * Get BitmapIndex for a specific dimension value.
     */
    public function getBitmap(string $dimension, string $value): ?BitmapIndex
    {
        if (!isset($this->reverseDictionaries[$dimension][$value])) {
            return null;
        }
        $dictId = $this->reverseDictionaries[$dimension][$value];
        return $this->bitmapIndexes[$dimension][$dictId] ?? null;
    }

    /**
     * Get all row IDs in this segment.
     *
     * @return array<int>
     */
    public function getAllRowIds(): array
    {
        return range(0, max(0, $this->rowCount - 1));
    }

    public function getTimestamp(int $rowId): int
    {
        return $this->timestamps[$rowId] ?? 0;
    }

    public function getDimensionValue(string $dimension, int $rowId): ?string
    {
        $dictId = $this->dimensionColumns[$dimension][$rowId] ?? null;
        if ($dictId === null) {
            return null;
        }
        return $this->dictionaries[$dimension][$dictId] ?? null;
    }

    public function getMetricValue(string $metric, int $rowId, float $default = 0.0): float
    {
        return $this->metricColumns[$metric][$rowId] ?? $default;
    }

    /**
     * @return array<string>
     */
    public function getDimensionNames(): array
    {
        return array_keys($this->dictionaries);
    }

    /**
     * @return array<string>
     */
    public function getMetricNames(): array
    {
        return array_keys($this->metricColumns);
    }

    /**
     * Serialize segment to JSON string.
     */
    public function serialize(): string
    {
        $bitmapsExport = [];
        foreach ($this->bitmapIndexes as $dim => $dictBitmaps) {
            $bitmapsExport[$dim] = [];
            foreach ($dictBitmaps as $dictId => $bm) {
                $bitmapsExport[$dim][$dictId] = $bm->getRowIds();
            }
        }

        $data = [
            'id' => $this->id,
            'dataSource' => $this->dataSource,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'rowCount' => $this->rowCount,
            'timestamps' => $this->timestamps,
            'dictionaries' => $this->dictionaries,
            'reverseDictionaries' => $this->reverseDictionaries,
            'dimensionColumns' => $this->dimensionColumns,
            'bitmapIndexes' => $bitmapsExport,
            'metricColumns' => $this->metricColumns,
        ];

        return (string)json_encode($data);
    }

    /**
     * Deserialize JSON string into ColumnarSegment instance.
     */
    public static function deserialize(string $json): ?self
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['id'], $data['dataSource'])) {
            return null;
        }

        $seg = new self(
            (string)$data['dataSource'],
            (int)$data['startTime'],
            (int)$data['endTime'],
            (string)$data['id']
        );

        $seg->rowCount = (int)$data['rowCount'];
        $seg->timestamps = (array)($data['timestamps'] ?? []);
        $seg->dictionaries = (array)($data['dictionaries'] ?? []);
        $seg->reverseDictionaries = (array)($data['reverseDictionaries'] ?? []);
        $seg->dimensionColumns = (array)($data['dimensionColumns'] ?? []);
        $seg->metricColumns = (array)($data['metricColumns'] ?? []);

        // Reconstitute Bitmaps
        $seg->bitmapIndexes = [];
        foreach ((array)($data['bitmapIndexes'] ?? []) as $dim => $dictBitmaps) {
            $seg->bitmapIndexes[$dim] = [];
            foreach ($dictBitmaps as $dictId => $rowIds) {
                $seg->bitmapIndexes[$dim][(int)$dictId] = new BitmapIndex((array)$rowIds);
            }
        }

        return $seg;
    }
}
