<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Metrics;

use NetFusion\Core\Observability\Metrics\Instruments\InstrumentInterface;
use NetFusion\Core\Observability\Metrics\Instruments\Counter;
use NetFusion\Core\Observability\Metrics\Instruments\UpDownCounter;
use NetFusion\Core\Observability\Metrics\Instruments\Gauge;
use NetFusion\Core\Observability\Metrics\Instruments\Histogram;

/**
 * Class Meter
 *
 * OpenTelemetry Meter for creating and registering metric instruments.
 */
class Meter
{
    protected string $name;
    protected string $version;
    /** @var array<string, InstrumentInterface> */
    protected array $instruments = [];

    public function __construct(string $name = 'netfusion-meter', string $version = '1.0.0')
    {
        $this->name = $name;
        $this->version = $version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function createCounter(string $name, string $description = '', string $unit = '1'): Counter
    {
        if (!isset($this->instruments[$name])) {
            $this->instruments[$name] = new Counter($name, $description, $unit);
        }
        return $this->instruments[$name];
    }

    public function createUpDownCounter(string $name, string $description = '', string $unit = '1'): UpDownCounter
    {
        if (!isset($this->instruments[$name])) {
            $this->instruments[$name] = new UpDownCounter($name, $description, $unit);
        }
        return $this->instruments[$name];
    }

    public function createGauge(string $name, string $description = '', string $unit = '1'): Gauge
    {
        if (!isset($this->instruments[$name])) {
            $this->instruments[$name] = new Gauge($name, $description, $unit);
        }
        return $this->instruments[$name];
    }

    public function createHistogram(string $name, string $description = '', string $unit = 'ms', array $boundaries = []): Histogram
    {
        if (!isset($this->instruments[$name])) {
            if (empty($boundaries)) {
                $this->instruments[$name] = new Histogram($name, $description, $unit);
            } else {
                $this->instruments[$name] = new Histogram($name, $description, $unit, $boundaries);
            }
        }
        return $this->instruments[$name];
    }

    public function getInstrument(string $name): ?InstrumentInterface
    {
        return $this->instruments[$name] ?? null;
    }

    /**
     * @return array<string, InstrumentInterface>
     */
    public function getInstruments(): array
    {
        return $this->instruments;
    }

    /**
     * Collect snapshot of all registered metric instruments.
     */
    public function collect(): array
    {
        $snapshot = [];
        foreach ($this->instruments as $name => $instrument) {
            $snapshot[$name] = $instrument->getData();
        }
        return $snapshot;
    }

    public function reset(): void
    {
        foreach ($this->instruments as $instrument) {
            $instrument->reset();
        }
    }
}
