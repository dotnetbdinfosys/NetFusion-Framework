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

namespace NetFusion\Core\Telemetry;

use InvalidArgumentException;
use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Telemetry\Context\Baggage;
use NetFusion\Core\Telemetry\Contracts\CounterInterface;
use NetFusion\Core\Telemetry\Contracts\ExporterInterface;
use NetFusion\Core\Telemetry\Contracts\GaugeInterface;
use NetFusion\Core\Telemetry\Contracts\HistogramInterface;
use NetFusion\Core\Telemetry\Contracts\MeterInterface;
use NetFusion\Core\Telemetry\Contracts\MetricInterface;
use NetFusion\Core\Telemetry\Exporters\JsonTelemetryExporter;
use NetFusion\Core\Telemetry\Exporters\OtlpHttpExporter;
use NetFusion\Core\Telemetry\Exporters\PrometheusExporter;
use NetFusion\Core\Telemetry\Metrics\MetricRegistry;
use NetFusion\Core\Tracing\TraceContext;

class TelemetryManager implements MeterInterface
{
    protected MetricRegistry $registry;
    /** @var array<string, ExporterInterface> */
    protected array $exporters = [];
    protected ?TraceContext $activeTraceContext = null;
    protected ?Baggage $activeBaggage = null;

    public function __construct(
        protected ?Application $app = null,
        ?MetricRegistry $registry = null
    ) {
        $this->registry = $registry ?? new MetricRegistry();
    }

    public function getRegistry(): MetricRegistry
    {
        return $this->registry;
    }

    public function counter(string $name, string $description = '', string $unit = '1'): CounterInterface
    {
        return $this->registry->counter($name, $description, $unit);
    }

    public function gauge(string $name, string $description = '', string $unit = '1'): GaugeInterface
    {
        return $this->registry->gauge($name, $description, $unit);
    }

    public function histogram(string $name, string $description = '', string $unit = 's', array $buckets = []): HistogramInterface
    {
        if (empty($buckets)) {
            $config = $this->getConfig();
            $buckets = $config['histogram_buckets']['default'] ?? [];
        }

        return $this->registry->histogram($name, $description, $unit, $buckets);
    }

    /**
     * Measure the execution time of a callable using a Histogram and increment execution counter.
     *
     * @template T
     * @param string $metricName
     * @param callable(): T $action
     * @param array<string, string|int|float|bool> $labels
     * @param string $description
     * @return T
     */
    public function measure(string $metricName, callable $action, array $labels = [], string $description = ''): mixed
    {
        $histogram = $this->histogram("{$metricName}_duration_seconds", $description, 's');
        $counter = $this->counter("{$metricName}_total", $description);

        $counter->inc($labels);
        return $histogram->time($action, $labels);
    }

    public function getMetric(string $name): ?MetricInterface
    {
        return $this->registry->getMetric($name);
    }

    /**
     * @return array<string, MetricInterface>
     */
    public function getMetrics(): array
    {
        return $this->registry->getMetrics();
    }

    public function reset(): void
    {
        $this->registry->reset();
    }

    // =========================================================================
    //  Standard Framework Metrics Helpers
    // =========================================================================

    public function recordHttpRequest(string $method, string $path, int $status, float $durationSeconds): void
    {
        $labels = [
            'method' => strtoupper($method),
            'path' => $path,
            'status' => (string)$status,
        ];

        $this->counter('http_requests_total', 'Total incoming HTTP requests count')
            ->inc($labels);

        $this->histogram('http_request_duration_seconds', 'HTTP request latency distribution in seconds', 's')
            ->record($durationSeconds, $labels);
    }

    public function recordQueueJob(string $jobName, string $status, float $durationSeconds): void
    {
        $labels = [
            'job' => $jobName,
            'status' => $status,
        ];

        $this->counter('queue_jobs_total', 'Total processed queue jobs')
            ->inc($labels);

        $this->histogram('queue_job_duration_seconds', 'Queue job processing duration in seconds', 's')
            ->record($durationSeconds, $labels);
    }

    public function recordDatabaseQuery(string $operation, float $durationSeconds, string $table = 'unknown'): void
    {
        $labels = [
            'operation' => strtoupper($operation),
            'table' => $table,
        ];

        $this->counter('db_queries_total', 'Total database queries executed')
            ->inc($labels);

        $this->histogram('db_query_duration_seconds', 'Database query execution latency in seconds', 's')
            ->record($durationSeconds, $labels);
    }

    // =========================================================================
    //  Exporters & Output Rendering
    // =========================================================================

    public function exporter(string $driver = 'prometheus'): ExporterInterface
    {
        if (!isset($this->exporters[$driver])) {
            $this->exporters[$driver] = match ($driver) {
                'prometheus' => new PrometheusExporter(),
                'json' => new JsonTelemetryExporter(),
                'otlp' => new OtlpHttpExporter(),
                default => throw new InvalidArgumentException("Unsupported telemetry exporter driver: [{$driver}]"),
            };
        }

        return $this->exporters[$driver];
    }

    public function renderPrometheus(): string
    {
        return (string)$this->exporter('prometheus')->export($this->getMetrics());
    }

    public function renderJson(): string
    {
        return (string)$this->exporter('json')->export($this->getMetrics());
    }

    // =========================================================================
    //  W3C Distributed TraceContext & Baggage
    // =========================================================================

    public function setTraceContext(?TraceContext $context): self
    {
        $this->activeTraceContext = $context;
        return $this;
    }

    public function getTraceContext(): ?TraceContext
    {
        return $this->activeTraceContext;
    }

    public function setBaggage(?Baggage $baggage): self
    {
        $this->activeBaggage = $baggage;
        return $this;
    }

    public function getBaggage(): Baggage
    {
        if ($this->activeBaggage === null) {
            $this->activeBaggage = new Baggage();
        }

        return $this->activeBaggage;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        if ($this->app !== null && $this->app->has(Config::class)) {
            $config = $this->app->make(Config::class)->get('telemetry', []);
            if (is_array($config)) {
                return $config;
            }
        }

        return [
            'histogram_buckets' => [
                'default' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
            ],
        ];
    }
}
