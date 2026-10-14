<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability;

use NetFusion\Core\Observability\Tracing\Tracer;
use NetFusion\Core\Observability\Tracing\Span;
use NetFusion\Core\Observability\Tracing\TraceContext;
use NetFusion\Core\Observability\Metrics\Meter;
use NetFusion\Core\Observability\Metrics\Instruments\Counter;
use NetFusion\Core\Observability\Metrics\Instruments\UpDownCounter;
use NetFusion\Core\Observability\Metrics\Instruments\Gauge;
use NetFusion\Core\Observability\Metrics\Instruments\Histogram;
use NetFusion\Core\Observability\Profiling\Profiler;
use NetFusion\Core\Observability\Exporters\OtlpHttpJsonExporter;
use NetFusion\Core\Observability\Exporters\PrometheusExporter;
use NetFusion\Core\Observability\Exporters\ZipkinExporter;

/**
 * Class ObservabilityManager
 *
 * Central master facade uniting OpenTelemetry Tracing, Metrics, Profiling, and Exporters.
 */
class ObservabilityManager
{
    protected static ?self $instance = null;
    protected array $config = [];
    protected Tracer $tracer;
    protected Meter $meter;
    protected Profiler $profiler;
    protected OtlpHttpJsonExporter $otlpExporter;
    protected PrometheusExporter $prometheusExporter;
    protected ZipkinExporter $zipkinExporter;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $serviceName = $config['service_name'] ?? 'netfusion-app';
        $serviceVersion = $config['service_version'] ?? '1.0.0';

        $this->tracer = new Tracer($serviceName, $serviceVersion, null, $config['resource_attributes'] ?? []);
        $this->meter = new Meter($serviceName, $serviceVersion);
        $this->profiler = new Profiler((int) ($config['profiler']['max_samples'] ?? 1000));

        $this->otlpExporter = new OtlpHttpJsonExporter($serviceName, $serviceVersion);
        $this->prometheusExporter = new PrometheusExporter();
        $this->zipkinExporter = new ZipkinExporter($serviceName);
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getTracer(): Tracer
    {
        return $this->tracer;
    }

    public function getMeter(): Meter
    {
        return $this->meter;
    }

    public function getProfiler(): Profiler
    {
        return $this->profiler;
    }

    // -------------------------------------------------------------
    // Tracing Convenience Methods
    // -------------------------------------------------------------

    public function startSpan(string $name, array $options = []): Span
    {
        return $this->tracer->startSpan($name, $options);
    }

    public function trace(string $name, callable $callable, array $options = []): mixed
    {
        return $this->tracer->span($name, $callable, $options);
    }

    public function getCurrentSpan(): ?Span
    {
        return $this->tracer->getCurrentSpan();
    }

    public function extractContext(array $headers): TraceContext
    {
        return $this->tracer->extractContext($headers);
    }

    public function injectContext(Span $span, array &$headers): void
    {
        $this->tracer->injectContext($span, $headers);
    }

    // -------------------------------------------------------------
    // Metrics Convenience Methods
    // -------------------------------------------------------------

    public function counter(string $name, string $description = '', string $unit = '1'): Counter
    {
        return $this->meter->createCounter($name, $description, $unit);
    }

    public function upDownCounter(string $name, string $description = '', string $unit = '1'): UpDownCounter
    {
        return $this->meter->createUpDownCounter($name, $description, $unit);
    }

    public function gauge(string $name, string $description = '', string $unit = '1'): Gauge
    {
        return $this->meter->createGauge($name, $description, $unit);
    }

    public function histogram(string $name, string $description = '', string $unit = 'ms', array $boundaries = []): Histogram
    {
        return $this->meter->createHistogram($name, $description, $unit, $boundaries);
    }

    public function collectMetrics(): array
    {
        return $this->meter->collect();
    }

    // -------------------------------------------------------------
    // Profiling Convenience Methods
    // -------------------------------------------------------------

    public function profile(string $label, callable $callable): mixed
    {
        return $this->profiler->profile($label, $callable);
    }

    public function getProfilingSummary(): array
    {
        return $this->profiler->getSummary();
    }

    public function getFlameGraphData(): string
    {
        return $this->profiler->toFlameGraphData();
    }

    // -------------------------------------------------------------
    // Exporter Methods
    // -------------------------------------------------------------

    public function exportOtlp(): array
    {
        $spans = $this->tracer->getBuffer()->drain();
        $metrics = $this->meter->collect();

        return [
            'traces' => $this->otlpExporter->exportTraces($spans),
            'metrics' => $this->otlpExporter->exportMetrics($metrics),
            'spans_count' => count($spans),
            'metrics_count' => count($metrics),
        ];
    }

    public function exportPrometheus(): string
    {
        return $this->prometheusExporter->exportMetrics($this->meter->collect());
    }

    public function exportZipkin(): array
    {
        $spans = $this->tracer->getBuffer()->all();
        return $this->zipkinExporter->exportTraces($spans);
    }
}
