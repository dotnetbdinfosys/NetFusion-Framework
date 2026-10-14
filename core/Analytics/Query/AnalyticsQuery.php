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
use NetFusion\Core\Analytics\Enums\Granularity;

/**
 * Analytical Query Definition Builder.
 * Specifies time range intervals, dimension filters, dimensions to GROUP BY, and metric aggregations.
 */
class AnalyticsQuery
{
    protected string $dataSource;
    protected int $startTime;
    protected int $endTime;
    protected Granularity $granularity = Granularity::ALL;
    /** @var array<string, string|array<string>> */
    protected array $filters = [];
    /** @var array<string> */
    protected array $dimensions = [];
    /** @var array<string, AggregationType> [outputName => [type, field]] */
    protected array $aggregations = [];
    protected ?int $limit = null;

    public function __construct(string $dataSource, int $startTime = 0, int $endTime = 2147483647)
    {
        $this->dataSource = $dataSource;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
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

    public function intervals(int $start, int $end): self
    {
        $this->startTime = $start;
        $this->endTime = $end;
        return $this;
    }

    public function granularity(Granularity $granularity): self
    {
        $this->granularity = $granularity;
        return $this;
    }

    public function getGranularity(): Granularity
    {
        return $this->granularity;
    }

    /**
     * Add an exact match filter for a dimension.
     *
     * @param string|array<string> $value
     */
    public function filter(string $dimension, string|array $value): self
    {
        $this->filters[$dimension] = $value;
        return $this;
    }

    /**
     * @return array<string, string|array<string>>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Group by specified dimension names.
     *
     * @param array<string> $dimensions
     */
    public function groupBy(array $dimensions): self
    {
        $this->dimensions = $dimensions;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    /**
     * Add an aggregation function.
     */
    public function aggregate(string $outputName, AggregationType $type, ?string $fieldName = null): self
    {
        $this->aggregations[$outputName] = [
            'type' => $type,
            'field' => $fieldName ?? $outputName,
        ];
        return $this;
    }

    public function sum(string $fieldName, ?string $outputName = null): self
    {
        return $this->aggregate($outputName ?? $fieldName, AggregationType::SUM, $fieldName);
    }

    public function count(?string $outputName = 'count'): self
    {
        return $this->aggregate($outputName, AggregationType::COUNT);
    }

    public function avg(string $fieldName, ?string $outputName = null): self
    {
        return $this->aggregate($outputName ?? ($fieldName . '_avg'), AggregationType::AVG, $fieldName);
    }

    public function max(string $fieldName, ?string $outputName = null): self
    {
        return $this->aggregate($outputName ?? ($fieldName . '_max'), AggregationType::MAX, $fieldName);
    }

    public function min(string $fieldName, ?string $outputName = null): self
    {
        return $this->aggregate($outputName ?? ($fieldName . '_min'), AggregationType::MIN, $fieldName);
    }

    public function hllUnique(string $fieldName, ?string $outputName = null): self
    {
        return $this->aggregate($outputName ?? ($fieldName . '_unique'), AggregationType::HLL_UNIQUE, $fieldName);
    }

    /**
     * @return array<string, array{type: AggregationType, field: string}>
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
