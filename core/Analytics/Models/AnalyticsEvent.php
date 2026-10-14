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

namespace NetFusion\Core\Analytics\Models;

/**
 * Represents a single analytical row ingested into the OLAP engine.
 * Comprises:
 * - __time: mandatory unix epoch millisecond or second timestamp
 * - dimensions: string/categorical attributes (indexed via dictionary & bitmap)
 * - metrics: numerical facts (sum, count, latency, revenue, etc.)
 */
class AnalyticsEvent
{
    protected int $timestamp;
    /** @var array<string, string> */
    protected array $dimensions;
    /** @var array<string, float|int> */
    protected array $metrics;

    /**
     * @param int $timestamp Unix timestamp (seconds or milliseconds)
     * @param array<string, mixed> $dimensions
     * @param array<string, float|int> $metrics
     */
    public function __construct(int $timestamp, array $dimensions = [], array $metrics = [])
    {
        $this->timestamp = $timestamp;

        $stringifiedDims = [];
        foreach ($dimensions as $k => $v) {
            $stringifiedDims[(string)$k] = (string)$v;
        }
        $this->dimensions = $stringifiedDims;

        $numericMetrics = [];
        foreach ($metrics as $k => $v) {
            $numericMetrics[(string)$k] = is_numeric($v) ? (float)$v : 0.0;
        }
        $this->metrics = $numericMetrics;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @return array<string, string>
     */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    public function getDimension(string $name, ?string $default = null): ?string
    {
        return $this->dimensions[$name] ?? $default;
    }

    /**
     * @return array<string, float|int>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getMetric(string $name, float $default = 0.0): float
    {
        return (float)($this->metrics[$name] ?? $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            '__time' => $this->timestamp,
            'dimensions' => $this->dimensions,
            'metrics' => $this->metrics,
        ];
    }
}
