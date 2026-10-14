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

namespace NetFusion\Core\Analytics\Query;

use NetFusion\Core\Analytics\Enums\AggregationType;
use NetFusion\Core\Analytics\Models\HyperLogLog;
use NetFusion\Core\Analytics\Storage\BitmapIndex;
use NetFusion\Core\Analytics\Storage\ColumnarSegment;
use NetFusion\Core\Analytics\Storage\SegmentManager;

/**
 * Scatter-Gather Analytical Query Engine (Apache Druid & ClickHouse Query Architecture).
 * Executes vectorized scatter-gather queries across distributed historical segments and real-time buffers.
 * Leverages inverted bitmap indexes for instant filtering without full row scans.
 */
class QueryExecutor
{
    protected SegmentManager $segmentManager;

    public function __construct(SegmentManager $segmentManager)
    {
        $this->segmentManager = $segmentManager;
    }

    /**
     * Execute an analytical query and return aggregated results.
     *
     * @return array<array<string, mixed>>
     */
    public function execute(AnalyticsQuery $query): array
    {
        $dataSource = $query->getDataSource();
        $startTime = $query->getStartTime();
        $endTime = $query->getEndTime();
        $granularity = $query->getGranularity();
        $filters = $query->getFilters();
        $dimensions = $query->getDimensions();
        $aggregations = $query->getAggregations();

        // Scatter: locate all relevant segments overlapping interval
        $segments = $this->segmentManager->getSegmentsForInterval($dataSource, $startTime, $endTime);

        /** @var array<string, array{bucket: int, dims: array<string, string>, acc: array<string, mixed>, count: int}> $accumulator */
        $accumulator = [];

        foreach ($segments as $segment) {
            $matchingRows = $this->evaluateFilters($segment, $filters, $startTime, $endTime);
            if (empty($matchingRows)) {
                continue;
            }

            foreach ($matchingRows as $rowId) {
                $rawTs = $segment->getTimestamp($rowId);
                $bucketTs = $granularity->bucket($rawTs);

                $dimVals = [];
                foreach ($dimensions as $dim) {
                    $dimVals[$dim] = $segment->getDimensionValue($dim, $rowId) ?? 'null';
                }

                $groupKey = $bucketTs . '|' . json_encode($dimVals);

                if (!isset($accumulator[$groupKey])) {
                    $initialAcc = [];
                    foreach ($aggregations as $outName => $spec) {
                        $type = $spec['type'];
                        $field = $spec['field'];
                        $val = $segment->getMetricValue($field, $rowId, 0.0);

                        switch ($type) {
                            case AggregationType::COUNT:
                                $initialAcc[$outName] = 1;
                                break;
                            case AggregationType::SUM:
                            case AggregationType::AVG:
                                $initialAcc[$outName] = $val;
                                break;
                            case AggregationType::MIN:
                                $initialAcc[$outName] = $val;
                                break;
                            case AggregationType::MAX:
                                $initialAcc[$outName] = $val;
                                break;
                            case AggregationType::HLL_UNIQUE:
                                $hll = new HyperLogLog();
                                // could be string from dimension or numeric from metric
                                $strVal = $segment->getDimensionValue($field, $rowId) ?? (string)$val;
                                $hll->add($strVal);
                                $initialAcc[$outName] = $hll;
                                break;
                        }
                    }

                    $accumulator[$groupKey] = [
                        'bucket' => $bucketTs,
                        'dims' => $dimVals,
                        'acc' => $initialAcc,
                        'count' => 1,
                    ];
                } else {
                    $accumulator[$groupKey]['count']++;
                    foreach ($aggregations as $outName => $spec) {
                        $type = $spec['type'];
                        $field = $spec['field'];
                        $val = $segment->getMetricValue($field, $rowId, 0.0);

                        switch ($type) {
                            case AggregationType::COUNT:
                                $accumulator[$groupKey]['acc'][$outName]++;
                                break;
                            case AggregationType::SUM:
                            case AggregationType::AVG:
                                $accumulator[$groupKey]['acc'][$outName] += $val;
                                break;
                            case AggregationType::MIN:
                                $accumulator[$groupKey]['acc'][$outName] = min($accumulator[$groupKey]['acc'][$outName], $val);
                                break;
                            case AggregationType::MAX:
                                $accumulator[$groupKey]['acc'][$outName] = max($accumulator[$groupKey]['acc'][$outName], $val);
                                break;
                            case AggregationType::HLL_UNIQUE:
                                $strVal = $segment->getDimensionValue($field, $rowId) ?? (string)$val;
                                if ($accumulator[$groupKey]['acc'][$outName] instanceof HyperLogLog) {
                                    $accumulator[$groupKey]['acc'][$outName]->add($strVal);
                                }
                                break;
                        }
                    }
                }
            }
        }

        // Gather & Finalize
        $results = [];
        foreach ($accumulator as $item) {
            $row = [];
            if ($granularity !== \NetFusion\Core\Analytics\Enums\Granularity::ALL) {
                $row['timestamp'] = $item['bucket'];
            }
            foreach ($item['dims'] as $dim => $v) {
                $row[$dim] = $v;
            }
            foreach ($item['acc'] as $outName => $val) {
                $type = $aggregations[$outName]['type'];
                if ($type === AggregationType::AVG) {
                    $row[$outName] = $item['count'] > 0 ? round($val / $item['count'], 4) : 0.0;
                } elseif ($type === AggregationType::HLL_UNIQUE && $val instanceof HyperLogLog) {
                    $row[$outName] = $val->cardinality();
                } else {
                    $row[$outName] = $val;
                }
            }
            $results[] = $row;
        }

        // Sort by timestamp if present
        if ($granularity !== \NetFusion\Core\Analytics\Enums\Granularity::ALL) {
            usort($results, fn ($a, $b) => ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0));
        }

        // Apply limit if specified
        if ($query->getLimit() !== null && $query->getLimit() > 0) {
            $results = array_slice($results, 0, $query->getLimit());
        }

        return $results;
    }

    /**
     * Vectorized evaluation of dimension filters using Bitmaps and interval range checking.
     *
     * @param array<string, string|array<string>> $filters
     * @return array<int> matching row IDs
     */
    protected function evaluateFilters(ColumnarSegment $segment, array $filters, int $startTime, int $endTime): array
    {
        /** @var BitmapIndex|null $resultBitmap */
        $resultBitmap = null;

        // Apply dimension bitmap filters
        foreach ($filters as $dim => $targetVal) {
            if (is_array($targetVal)) {
                // OR filter for multiple values
                $orBitmap = new BitmapIndex();
                foreach ($targetVal as $val) {
                    $bm = $segment->getBitmap($dim, (string)$val);
                    if ($bm !== null) {
                        $orBitmap = $orBitmap->or($bm);
                    }
                }
                $resultBitmap = $resultBitmap === null ? $orBitmap : $resultBitmap->and($orBitmap);
            } else {
                $bm = $segment->getBitmap($dim, (string)$targetVal);
                if ($bm === null) {
                    return []; // No matches for this required filter value
                }
                $resultBitmap = $resultBitmap === null ? $bm : $resultBitmap->and($bm);
            }
        }

        $candidateRows = $resultBitmap !== null ? $resultBitmap->getRowIds() : $segment->getAllRowIds();

        // Filter candidates by time interval [startTime, endTime]
        $finalRows = [];
        foreach ($candidateRows as $rowId) {
            $ts = $segment->getTimestamp($rowId);
            if ($ts >= $startTime && $ts <= $endTime) {
                $finalRows[] = $rowId;
            }
        }

        return $finalRows;
    }
}
