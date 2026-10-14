<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\Observability\Tracing\SpanKind;
use NetFusion\Core\Observability\Tracing\SpanStatusCode;
use NetFusion\Core\Observability\Tracing\TraceContext;
use NetFusion\Core\Observability\Tracing\Span;
use NetFusion\Core\Observability\Tracing\SpanBuffer;
use NetFusion\Core\Observability\Tracing\Tracer;
use NetFusion\Core\Observability\Metrics\Instruments\Counter;
use NetFusion\Core\Observability\Metrics\Instruments\UpDownCounter;
use NetFusion\Core\Observability\Metrics\Instruments\Gauge;
use NetFusion\Core\Observability\Metrics\Instruments\Histogram;
use NetFusion\Core\Observability\Metrics\Meter;
use NetFusion\Core\Observability\Profiling\ProfileSample;
use NetFusion\Core\Observability\Profiling\Profiler;
use NetFusion\Core\Observability\Exporters\OtlpHttpJsonExporter;
use NetFusion\Core\Observability\Exporters\PrometheusExporter;
use NetFusion\Core\Observability\Exporters\ZipkinExporter;
use NetFusion\Core\Observability\ObservabilityManager;
use NetFusion\Core\Console\Commands\OtelExportCommand;
use NetFusion\Core\Console\Commands\OtelMetricsCommand;
use NetFusion\Core\Console\Commands\OtelProfileCommand;

$app = new Application(realpath(__DIR__ . '/..'));

$passed = 0;
$failed = 0;

function it(string $desc, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  [PASS] {$desc}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$desc} -> " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

function expect(mixed $actual) {
    return new class($actual) {
        public function __construct(private mixed $actual) {}
        public function toBe(mixed $expected): void {
            if ($this->actual !== $expected) {
                throw new \AssertionError("Expected " . var_export($expected, true) . ", got " . var_export($this->actual, true));
            }
        }
        public function toBeTrue(): void {
            if ($this->actual !== true) {
                throw new \AssertionError("Expected true, got " . var_export($this->actual, true));
            }
        }
        public function toBeFalse(): void {
            if ($this->actual !== false) {
                throw new \AssertionError("Expected false, got " . var_export($this->actual, true));
            }
        }
        public function toBeGreaterThan(mixed $expected): void {
            if ($this->actual <= $expected) {
                throw new \AssertionError("Expected {$this->actual} to be > {$expected}");
            }
        }
        public function toBeGreaterThanOrEqual(mixed $expected): void {
            if ($this->actual < $expected) {
                throw new \AssertionError("Expected {$this->actual} to be >= {$expected}");
            }
        }
        public function toBeLessThanOrEqual(mixed $expected): void {
            if ($this->actual > $expected) {
                throw new \AssertionError("Expected {$this->actual} to be <= {$expected}");
            }
        }
        public function toContain(string $needle): void {
            if (!str_contains(strtolower((string) $this->actual), strtolower($needle))) {
                throw new \AssertionError("Expected string to contain '{$needle}', got '{$this->actual}'");
            }
        }
    };
}

echo "\n--- Phase 73: OpenTelemetry Observability Fabric Test Suite ---\n\n";

ObservabilityManager::resetInstance();

// ==========================================
// 1. W3C TraceContext & Baggage Tests
// ==========================================
it('TraceContext parses and formats W3C traceparent headers', function () {
    $rawHeader = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
    $context = TraceContext::fromTraceparent($rawHeader);

    expect($context !== null)->toBeTrue();
    expect($context->getTraceId())->toBe('4bf92f3577b34da6a3ce929d0e0e4736');
    expect($context->getSpanId())->toBe('00f067aa0ba902b7');
    expect($context->isSampled())->toBeTrue();
    expect($context->toTraceparent())->toBe($rawHeader);

    // Invalid traceparent
    expect(TraceContext::fromTraceparent('invalid-format'))->toBe(null);
    expect(TraceContext::fromTraceparent('00-00000000000000000000000000000000-00f067aa0ba902b7-01'))->toBe(null); // All zeros
});

it('TraceContext handles tracestate and baggage propagation', function () {
    $context = new TraceContext();
    $context->setTraceStateValue('rojo', '1');
    $context->setTraceStateValue('congo', '2');
    expect($context->toTracestate())->toBe('rojo=1,congo=2');

    $context->setBaggageItem('user.id', 'usr_9921');
    $context->setBaggageItem('region', 'us-east-1');
    expect($context->getBaggageItem('user.id'))->toBe('usr_9921');

    $headers = [];
    $context->injectIntoHeaders($headers);
    expect(isset($headers['traceparent']))->toBeTrue();
    expect(isset($headers['tracestate']))->toBeTrue();
    expect(isset($headers['baggage']))->toBeTrue();

    // Extract from headers
    $extracted = TraceContext::extractFromHeaders($headers);
    expect($extracted->getTraceId())->toBe($context->getTraceId());
    expect($extracted->getBaggageItem('user.id'))->toBe('usr_9921');

    // Child context fork
    $child = $extracted->forkChild();
    expect($child->getTraceId())->toBe($extracted->getTraceId());
    expect($child->getSpanId() !== $extracted->getSpanId())->toBeTrue();
    expect($child->getBaggageItem('region'))->toBe('us-east-1');
});

// ==========================================
// 2. OpenTelemetry Span Tests
// ==========================================
it('Span records attributes, events, status and exceptions', function () {
    $ctx = new TraceContext();
    $span = new Span('db.query', $ctx, null, SpanKind::CLIENT, ['db.system' => 'mysql']);

    expect($span->getName())->toBe('db.query');
    expect($span->getKind())->toBe('CLIENT');
    expect($span->getAttribute('db.system'))->toBe('mysql');
    expect($span->isEnded())->toBeFalse();

    $span->setAttribute('db.statement', 'SELECT * FROM users WHERE id = ?');
    $span->addEvent('cache_miss', ['key' => 'user_1']);
    expect(count($span->getEvents()))->toBe(1);

    // Record exception
    $span->recordException(new \RuntimeException("Connection lost", 500));
    expect($span->getStatusCode())->toBe(SpanStatusCode::ERROR);
    expect($span->getStatusMessage())->toBe('Connection lost');
    expect(count($span->getEvents()))->toBe(2); // cache_miss + exception

    $span->end();
    expect($span->isEnded())->toBeTrue();
    expect($span->getDurationMs())->toBeGreaterThanOrEqual(0.0);

    $arr = $span->toArray();
    expect($arr['name'])->toBe('db.query');
    expect($arr['status']['code'])->toBe(SpanStatusCode::ERROR);
    expect(is_string($span->toJson()))->toBeTrue();
});

// ==========================================
// 3. SpanBuffer Tests
// ==========================================
it('SpanBuffer holds spans and supports capacity eviction and drain', function () {
    $buffer = new SpanBuffer(2);
    $ctx = new TraceContext();

    $s1 = new Span('s1', $ctx);
    $s2 = new Span('s2', $ctx);
    $s3 = new Span('s3', $ctx);

    $buffer->push($s1);
    $buffer->push($s2);
    expect($buffer->count())->toBe(2);

    // Pushing third drops oldest ($s1)
    $buffer->push($s3);
    expect($buffer->count())->toBe(2);

    $drained = $buffer->drain();
    expect(count($drained))->toBe(2);
    expect($drained[0]->getName())->toBe('s2');
    expect($drained[1]->getName())->toBe('s3');
    expect($buffer->count())->toBe(0);
});

// ==========================================
// 4. Tracer & Active Scope Tests
// ==========================================
it('Tracer manages active span stack and auto-closing callable scope', function () {
    $tracer = new Tracer('order-service', '2.1.0');
    expect($tracer->getCurrentSpan())->toBe(null);

    // Start parent span
    $parent = $tracer->startSpan('http.request', ['kind' => SpanKind::SERVER]);
    expect($tracer->getCurrentSpan())->toBe($parent);

    // Start child span
    $child = $tracer->startSpan('process.payment', ['kind' => SpanKind::INTERNAL]);
    expect($tracer->getCurrentSpan())->toBe($child);
    expect($child->getParentSpanId())->toBe($parent->getSpanId());
    expect($child->getTraceId())->toBe($parent->getTraceId());

    $tracer->endSpan($child);
    expect($tracer->getCurrentSpan())->toBe($parent);

    $tracer->endSpan($parent);
    expect($tracer->getCurrentSpan())->toBe(null);
    expect($tracer->getBuffer()->count())->toBe(2);

    // Callable span wrapper
    $res = $tracer->span('auth.check', function ($span) {
        $span->setAttribute('auth.success', true);
        return 'authenticated';
    });
    expect($res)->toBe('authenticated');
    expect($tracer->getBuffer()->count())->toBe(3);
});

// ==========================================
// 5. Metric Instruments Tests
// ==========================================
it('Counter increments monotonically and tracks attributes', function () {
    $counter = new Counter('http_requests_total', 'Total HTTP requests', '1');
    $counter->add(1, ['method' => 'GET', 'status' => '200']);
    $counter->add(4, ['method' => 'GET', 'status' => '200']);
    $counter->add(2, ['method' => 'POST', 'status' => '201']);

    expect($counter->getValue(['method' => 'GET', 'status' => '200']))->toBe(5);
    expect($counter->getValue(['method' => 'POST', 'status' => '201']))->toBe(2);

    // Non-negative validation
    $caught = false;
    try {
        $counter->add(-1);
    } catch (\InvalidArgumentException $e) {
        $caught = true;
    }
    expect($caught)->toBeTrue();
});

it('UpDownCounter supports positive and negative deltas', function () {
    $ud = new UpDownCounter('active_connections', 'Current active connections', '1');
    $ud->add(5);
    expect($ud->getValue())->toBe(5);

    $ud->add(-2);
    expect($ud->getValue())->toBe(3);
});

it('Gauge tracks instantaneous point-in-time value', function () {
    $gauge = new Gauge('system_memory_usage_bytes', 'Physical memory usage', 'bytes');
    $gauge->record(1024 * 1024 * 16, ['host' => 'srv-1']);
    expect($gauge->getValue(['host' => 'srv-1']))->toBe(1024 * 1024 * 16);

    $gauge->record(1024 * 1024 * 32, ['host' => 'srv-1']);
    expect($gauge->getValue(['host' => 'srv-1']))->toBe(1024 * 1024 * 32);
});

it('Histogram records bucket distributions and calculates percentiles', function () {
    $hist = new Histogram('http_request_duration_ms', 'Request latency', 'ms', [10.0, 50.0, 100.0, 500.0]);

    // Record 100 values
    for ($i = 1; $i <= 100; $i++) {
        $hist->record((float) $i, ['route' => '/api/users']);
    }

    $data = $hist->getData();
    expect($data['type'])->toBe('histogram');
    $point = $data['points'][0];
    expect($point['count'])->toBe(100);
    expect($point['sum'])->toBe(5050.0);
    expect($point['min'])->toBe(1.0);
    expect($point['max'])->toBe(100.0);

    // Percentile verification
    expect($hist->getPercentile(50, ['route' => '/api/users']))->toBeGreaterThanOrEqual(49.0);
    expect($hist->getPercentile(90, ['route' => '/api/users']))->toBeGreaterThanOrEqual(89.0);
    expect($hist->getPercentile(99, ['route' => '/api/users']))->toBeGreaterThanOrEqual(98.0);
});

// ==========================================
// 6. Meter Tests
// ==========================================
it('Meter creates and aggregates instruments snapshot', function () {
    $meter = new Meter('api-meter', '1.0.0');
    $c = $meter->createCounter('api_hits');
    $c->add(10);

    $g = $meter->createGauge('active_workers');
    $g->record(4);

    $h = $meter->createHistogram('query_time');
    $h->record(25.5);

    $snapshot = $meter->collect();
    expect(isset($snapshot['api_hits']))->toBeTrue();
    expect(isset($snapshot['active_workers']))->toBeTrue();
    expect(isset($snapshot['query_time']))->toBeTrue();
});

// ==========================================
// 7. Profiler & FlameGraph Tests
// ==========================================
it('Profiler tracks duration, memory allocation, and generates FlameGraph lines', function () {
    $profiler = new Profiler();

    $res = $profiler->profile('json_serialization', function () {
        $data = [];
        for ($i = 0; $i < 500; $i++) {
            $data[] = ['id' => $i, 'name' => "item_{$i}"];
        }
        return json_encode($data);
    });

    expect(is_string($res))->toBeTrue();
    expect(count($profiler->getSamples()))->toBe(1);

    $sample = $profiler->getSamples()[0];
    expect($sample->getLabel())->toBe('json_serialization');
    expect($sample->getWallDurationMs())->toBeGreaterThanOrEqual(0.0);
    expect($sample->getMemoryPeakBytes())->toBeGreaterThan(0);

    // Flamegraph format
    $flameData = $profiler->toFlameGraphData();
    expect(is_string($flameData))->toBeTrue();
    expect($flameData)->toContain('json_serialization');

    // Summary
    $summary = $profiler->getSummary();
    expect($summary['total_samples'])->toBe(1);
    expect($summary['total_wall_duration_ms'])->toBeGreaterThanOrEqual(0.0);
});

// ==========================================
// 8. Exporters Tests
// ==========================================
it('OtlpHttpJsonExporter formats traces and metrics according to OTel JSON schema', function () {
    $exporter = new OtlpHttpJsonExporter('checkout-service', '1.0.0');
    $ctx = new TraceContext();
    $span = new Span('charge_card', $ctx, null, SpanKind::CLIENT, ['amount' => 100, 'currency' => 'USD']);
    $span->end();

    $otlpTraces = $exporter->exportTraces([$span]);
    expect(isset($otlpTraces['resourceSpans']))->toBeTrue();
    expect($otlpTraces['resourceSpans'][0]['resource']['attributes'][0]['key'])->toBe('service.name');
    expect($otlpTraces['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['name'])->toBe('charge_card');

    $meter = new Meter();
    $meter->createCounter('checkout_total')->add(1);
    $otlpMetrics = $exporter->exportMetrics($meter->collect());
    expect(isset($otlpMetrics['resourceMetrics']))->toBeTrue();
    expect($otlpMetrics['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['name'])->toBe('checkout_total');
});

it('PrometheusExporter formats metrics into standard exposition format', function () {
    $exporter = new PrometheusExporter();
    $meter = new Meter();
    $meter->createCounter('http_requests_total', 'Total HTTP requests')->add(42, ['status' => '200']);
    $meter->createGauge('active_sessions')->record(5);
    $meter->createHistogram('response_time', '', 'ms', [10.0, 50.0])->record(22.0);

    $output = $exporter->exportMetrics($meter->collect());
    expect($output)->toContain('# TYPE http_requests_total counter');
    expect($output)->toContain('http_requests_total{status="200"} 42');
    expect($output)->toContain('# TYPE active_sessions gauge');
    expect($output)->toContain('active_sessions 5');
    expect($output)->toContain('response_time_bucket{le="10"} 0');
    expect($output)->toContain('response_time_bucket{le="50"} 1');
    expect($output)->toContain('response_time_count 1');
});

it('ZipkinExporter formats traces conforming to Zipkin v2 JSON', function () {
    $exporter = new ZipkinExporter('inventory-service');
    $ctx = new TraceContext();
    $span = new Span('check_stock', $ctx, null, SpanKind::SERVER);
    $span->end();

    $zipkin = $exporter->exportTraces([$span]);
    expect(is_array($zipkin))->toBeTrue();
    expect(count($zipkin))->toBe(1);
    expect($zipkin[0]['name'])->toBe('check_stock');
    expect($zipkin[0]['localEndpoint']['serviceName'])->toBe('inventory-service');
    expect(isset($zipkin[0]['timestamp'], $zipkin[0]['duration']))->toBeTrue();
});

// ==========================================
// 9. ObservabilityManager Facade & Global Helpers
// ==========================================
it('ObservabilityManager facade unites tracing, metrics, profiling, and exporters', function () {
    ObservabilityManager::resetInstance();
    $otel = otel();
    expect($otel instanceof ObservabilityManager)->toBeTrue();

    // Trace helper
    $traced = otel_span('facade.work', function ($span) {
        $span->setAttribute('facade.test', true);
        return 'done';
    });
    expect($traced)->toBe('done');

    // Metrics helpers
    $counter = otel_counter('helper_counter');
    $counter->add(5);
    expect($counter->getValue())->toBe(5);

    $gauge = otel_gauge('helper_gauge');
    $gauge->record(12);
    expect($gauge->getValue())->toBe(12);

    $hist = otel_histogram('helper_hist');
    $hist->record(30.0);
    expect($hist->getData()['points'][0]['count'])->toBe(1);

    // Profiling helper
    $profRes = $otel->profile('helper_prof', function () {
        return strlen(str_repeat('A', 1000));
    });
    expect($profRes)->toBe(1000);

    // Export helpers
    $otlp = $otel->exportOtlp();
    expect(isset($otlp['traces'], $otlp['metrics']))->toBeTrue();

    $prom = $otel->exportPrometheus();
    expect($prom)->toContain('helper_counter');

    $zipkin = $otel->exportZipkin();
    expect(is_array($zipkin))->toBeTrue();
});

// ==========================================
// 10. CLI Commands Tests
// ==========================================
it('Observability CLI Commands execute with zero errors', function () {
    global $app;

    // 1. otel:export
    $exportCmd = new OtelExportCommand($app);
    ob_start();
    $code1 = $exportCmd->handle(['--format=otlp']);
    $out1 = ob_get_clean();
    expect($code1)->toBe(0);
    expect($out1)->toContain('resourceSpans');

    // 2. otel:metrics
    $metricsCmd = new OtelMetricsCommand($app);
    ob_start();
    $code2 = $metricsCmd->handle(['--format=prometheus']);
    $out2 = ob_get_clean();
    expect($code2)->toBe(0);
    expect($out2)->toContain('helper_counter');

    // 3. otel:profile
    $profileCmd = new OtelProfileCommand($app);
    ob_start();
    $code3 = $profileCmd->handle(['--format=summary']);
    $out3 = ob_get_clean();
    expect($code3)->toBe(0);
    expect($out3)->toContain('Total Samples Recorded');
});

// ==========================================
// 11. Migration Syntax Tests
// ==========================================
it('Observability migration file is valid and exposes up and down methods', function () {
    $migration = require __DIR__ . '/../database/migrations/2026_09_27_000056_create_observability_tables.php';
    expect(is_object($migration))->toBeTrue();
    expect(method_exists($migration, 'up'))->toBeTrue();
    expect(method_exists($migration, 'down'))->toBeTrue();
});

// ==========================================
// 12. Edge-case & Boundary Tests
// ==========================================
it('TraceContext preserves flags and baggage serialization with special characters', function () {
    $ctx = new TraceContext(null, null, 0x00);
    expect($ctx->isSampled())->toBeFalse();
    $ctx->setSampled(true);
    expect($ctx->isSampled())->toBeTrue();

    $ctx->setBaggageItem('user name', 'Md. Ashish & Co');
    $bagHeader = $ctx->toBaggage();
    expect($bagHeader)->toContain('user%20name=Md.%20Ashish%20%26%20Co');
    $parsed = TraceContext::parseBaggage($bagHeader);
    expect($parsed['user name'])->toBe('Md. Ashish & Co');
});

it('Span records links and updates names dynamically', function () {
    $ctx = new TraceContext();
    $span = new Span('temp_name', $ctx);
    $span->updateName('final_name');
    expect($span->getName())->toBe('final_name');

    $span->addLink('trace_abc', 'span_123', ['link.type' => 'parent']);
    expect(count($span->getLinks()))->toBe(1);
    expect($span->getLinks()[0]['trace_id'])->toBe('trace_abc');
});

it('Histogram handles empty percentile and custom boundary placement', function () {
    $hist = new Histogram('latency_test', '', 'ms', [10.0, 50.0]);
    expect($hist->getPercentile(50))->toBe(0.0);

    $hist->record(5.0);  // <= 10
    $hist->record(20.0); // <= 50
    $hist->record(99.0); // > 50 (+Inf)

    $data = $hist->getData()['points'][0];
    expect($data['bucket_counts'][0])->toBe(1); // <= 10
    expect($data['bucket_counts'][1])->toBe(1); // <= 50
    expect($data['bucket_counts'][2])->toBe(1); // +Inf
});

it('Profiler throws on unknown session ID and clears samples', function () {
    $profiler = new Profiler();
    $caught = false;
    try {
        $profiler->stop('non_existent_session');
    } catch (\InvalidArgumentException $e) {
        $caught = true;
    }
    expect($caught)->toBeTrue();

    $profiler->profile('work', function () { return 1; });
    expect(count($profiler->getSamples()))->toBe(1);
    $profiler->clear();
    expect(count($profiler->getSamples()))->toBe(0);
});

it('Exporters handle complex attributes and file output', function () {
    $exporter = new OtlpHttpJsonExporter('app', '1.0.0');
    $ctx = new TraceContext();
    $span = new Span('nested_test', $ctx, null, SpanKind::INTERNAL, [
        'is_admin' => true,
        'retry_count' => 3,
        'temperature' => 98.6,
        'tags' => ['vip', 'cloud'],
    ]);
    $span->end();

    $otlp = $exporter->exportTraces([$span]);
    expect(isset($otlp['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['attributes']))->toBeTrue();

    // Test export command with file writing
    global $app;
    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'otel_test_export_' . uniqid() . '.json';
    $cmd = new OtelExportCommand($app);
    ob_start();
    $cmd->handle(['--format=otlp', "--output={$tmpFile}"]);
    ob_get_clean();

    expect(file_exists($tmpFile))->toBeTrue();
    $saved = json_decode(file_get_contents($tmpFile), true);
    expect(is_array($saved))->toBeTrue();
    @unlink($tmpFile);
});

echo "\n--- Phase 73 Test Summary ---\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
