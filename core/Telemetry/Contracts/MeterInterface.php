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

namespace NetFusion\Core\Telemetry\Contracts;

interface MeterInterface
{
    public function counter(string $name, string $description = '', string $unit = '1'): CounterInterface;
    public function gauge(string $name, string $description = '', string $unit = '1'): GaugeInterface;
    public function histogram(string $name, string $description = '', string $unit = 's', array $buckets = []): HistogramInterface;
    public function getMetric(string $name): ?MetricInterface;
    /**
     * @return array<string, MetricInterface>
     */
    public function getMetrics(): array;
    public function reset(): void;
}
