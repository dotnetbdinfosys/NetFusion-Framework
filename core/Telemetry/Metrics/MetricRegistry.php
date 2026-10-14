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

namespace NetFusion\Core\Telemetry\Metrics;

use NetFusion\Core\Telemetry\Contracts\CounterInterface;
use NetFusion\Core\Telemetry\Contracts\GaugeInterface;
use NetFusion\Core\Telemetry\Contracts\HistogramInterface;
use NetFusion\Core\Telemetry\Contracts\MeterInterface;
use NetFusion\Core\Telemetry\Contracts\MetricInterface;

class MetricRegistry implements MeterInterface
{
    /** @var array<string, MetricInterface> */
    protected array $metrics = [];

    public function counter(string $name, string $description = '', string $unit = '1'): CounterInterface
    {
        if (isset($this->metrics[$name])) {
            if ($this->metrics[$name] instanceof CounterInterface) {
                return $this->metrics[$name];
            }
        }

        $counter = new Counter($name, $description, $unit);
        $this->metrics[$name] = $counter;
        return $counter;
    }

    public function gauge(string $name, string $description = '', string $unit = '1'): GaugeInterface
    {
        if (isset($this->metrics[$name])) {
            if ($this->metrics[$name] instanceof GaugeInterface) {
                return $this->metrics[$name];
            }
        }

        $gauge = new Gauge($name, $description, $unit);
        $this->metrics[$name] = $gauge;
        return $gauge;
    }

    public function histogram(string $name, string $description = '', string $unit = 's', array $buckets = []): HistogramInterface
    {
        if (isset($this->metrics[$name])) {
            if ($this->metrics[$name] instanceof HistogramInterface) {
                return $this->metrics[$name];
            }
        }

        $histogram = new Histogram($name, $description, $unit, $buckets);
        $this->metrics[$name] = $histogram;
        return $histogram;
    }

    public function register(MetricInterface $metric): self
    {
        $this->metrics[$metric->getName()] = $metric;
        return $this;
    }

    public function getMetric(string $name): ?MetricInterface
    {
        return $this->metrics[$name] ?? null;
    }

    /**
     * @return array<string, MetricInterface>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function reset(): void
    {
        foreach ($this->metrics as $metric) {
            $metric->reset();
        }
    }

    public function clear(): void
    {
        $this->metrics = [];
    }
}
