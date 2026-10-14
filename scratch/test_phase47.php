<?php
declare(strict_types=1);

/**
 * NetFusion Framework - Phase 47 Test Suite
 * Enterprise OpenTelemetry Distributed Tracing, Metrics Collector & Prometheus Exporter Engine
 *
 * @author DotNetBD <support@dotnetbd.com>
 * @license MIT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Telemetry\Context\Baggage;
use NetFusion\Core\Telemetry\Context\TraceContextPropagator;
use NetFusion\Core\Telemetry\Exporters\JsonTelemetryExporter;
use NetFusion\Core\Telemetry\Exporters\OtlpHttpExporter;
use NetFusion\Core\Telemetry\Exporters\PrometheusExporter;
use NetFusion\Core\Telemetry\Http\PrometheusMetricsController;
use NetFusion\Core\Telemetry\Http\TraceContextMiddleware;
use NetFusion\Core\Telemetry\Metrics\Counter;
use NetFusion\Core\Telemetry\Metrics\Gauge;
use NetFusion\Core\Telemetry\Metrics\Histogram;
use NetFusion\Core\Telemetry\Metrics\MetricRegistry;
use NetFusion\Core\Telemetry\TelemetryManager;
use NetFusion\Core\Tracing\TraceContext;

$passed = 0;
$failed = 0;

function assertTest(string $title, bool $condition, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "\033[32m✔ [PASS]\033[0m {$title}" . PHP_EOL;
    } else {
        $failed++;
        echo "\033[31m✖ [FAIL]\033[0m {$title} " . ($details ? "({$details})" : '') . PHP_EOL;
    }
}

echo "\n=======================================================\n";
echo "   NetFusion Phase 47: OpenTelemetry & Metrics Suite   \n";
echo "=======================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Counter Metric Tests
// -----------------------------------------------------------------------------
echo "\n--- 1. Counter Metric ---\n";

$counter = new Counter('http_requests_total', 'Total HTTP requests count');
$counter->inc(['method' => 'GET', 'status' => '200']);
$counter->inc(['method' => 'GET', 'status' => '200']);
$counter->add(5, ['method' => 'POST', 'status' => '201']);

assertTest('Counter: increment and add on labels', $counter->get(['method' => 'GET', 'status' => '200']) === 2 && $counter->get(['method' => 'POST', 'status' => '201']) === 5);
assertTest('Counter: non-existent label combination returns 0', $counter->get(['method' => 'DELETE', 'status' => '404']) === 0);

$negCaught = false;
try {
    $counter->add(-1);
} catch (\InvalidArgumentException) {
    $negCaught = true;
}
assertTest('Counter: rejects negative increment amount', $negCaught);

$samples = $counter->collect();
assertTest('Counter: collect returns MetricSample array with _total suffix', count($samples) === 2 && str_ends_with($samples[0]->getMetricName(), '_total'));

// -----------------------------------------------------------------------------
// 2. Gauge Metric Tests
// -----------------------------------------------------------------------------
echo "\n--- 2. Gauge Metric ---\n";

$gauge = new Gauge('system_memory_usage_bytes', 'Current memory usage in bytes', 'bytes');
$gauge->set(1048576, ['node' => 'app-1']);
assertTest('Gauge: set value', $gauge->get(['node' => 'app-1']) === 1048576);

$gauge->inc(512, ['node' => 'app-1']);
assertTest('Gauge: increment value', $gauge->get(['node' => 'app-1']) === 1049088);

$gauge->dec(1000, ['node' => 'app-1']);
assertTest('Gauge: decrement value', $gauge->get(['node' => 'app-1']) === 1048088);

$gauge->setToCurrentTime(['node' => 'app-1']);
assertTest('Gauge: setToCurrentTime updates to unix timestamp', $gauge->get(['node' => 'app-1']) >= (time() - 2));

// -----------------------------------------------------------------------------
// 3. Histogram Metric Tests
// -----------------------------------------------------------------------------
echo "\n--- 3. Histogram Metric ---\n";

$histogram = new Histogram(
    name: 'http_request_duration_seconds',
    description: 'HTTP request duration distribution',
    unit: 's',
    buckets: [0.01, 0.05, 0.1, 0.5, 1.0, 5.0]
);

$histogram->record(0.008, ['handler' => 'api']); // <= 0.01
$histogram->record(0.045, ['handler' => 'api']); // <= 0.05
$histogram->record(0.250, ['handler' => 'api']); // <= 0.5
$histogram->record(1.200, ['handler' => 'api']); // <= 5.0

$histSamples = $histogram->collect();
// Buckets count: 6 custom + 1 (+Inf) = 7 bucket samples + 1 sum + 1 count = 9 samples
assertTest('Histogram: collect generates expected bucket, sum, and count samples', count($histSamples) === 9);

$sumSample = null;
$countSample = null;
foreach ($histSamples as $s) {
    if ($s->suffix === '_sum') $sumSample = $s;
    if ($s->suffix === '_count') $countSample = $s;
}

assertTest('Histogram: sum and count match recorded values', $countSample !== null && $countSample->value === 4 && $sumSample !== null && abs($sumSample->value - 1.503) < 0.001);

// Timing helper
$timedResult = $histogram->time(function () {
    usleep(5000); // 5ms
    return 'timed_ok';
}, ['handler' => 'timed']);

assertTest('Histogram: time() measures duration and returns callback result', $timedResult === 'timed_ok');

// -----------------------------------------------------------------------------
// 4. W3C Distributed TraceContext & Baggage
// -----------------------------------------------------------------------------
echo "\n--- 4. W3C Baggage & TraceContext Propagation ---\n";

$baggage = new Baggage();
$baggage->set('userId', 'alice', ['role' => 'admin']);
$baggage->set('serverNode', 'dfw-01');

$headerStr = $baggage->toHeader();
assertTest('Baggage: serialized to valid W3C header format', str_contains($headerStr, 'userId=alice') && str_contains($headerStr, 'serverNode=dfw-01'));

$parsedBaggage = Baggage::fromHeader($headerStr);
assertTest('Baggage: parsed from header preserves keys, values and metadata', $parsedBaggage->get('userId') === 'alice' && $parsedBaggage->get('serverNode') === 'dfw-01');

$traceContext = TraceContext::generate();
$carrier = [];
TraceContextPropagator::inject($carrier, $traceContext, $baggage);

assertTest('TraceContextPropagator: inject attaches traceparent and baggage', isset($carrier['traceparent']) && isset($carrier['baggage']));

$extracted = TraceContextPropagator::extract($carrier);
assertTest('TraceContextPropagator: extract recovers TraceContext and Baggage', $extracted['traceContext'] instanceof TraceContext && $extracted['traceContext']->traceId === $traceContext->traceId && $extracted['baggage']->get('userId') === 'alice');

// -----------------------------------------------------------------------------
// 5. Exporters (Prometheus, JSON, OTLP)
// -----------------------------------------------------------------------------
echo "\n--- 5. Telemetry Exporters ---\n";

$registry = new MetricRegistry();
$regCounter = $registry->counter('api_requests_total', 'API request counter');
$regCounter->inc(['method' => 'GET', 'code' => '200']);

$regGauge = $registry->gauge('active_connections', 'Current active TCP connections');
$regGauge->set(42);

$promExporter = new PrometheusExporter();
$promOutput = $promExporter->export($registry->getMetrics());

assertTest('PrometheusExporter: contains # HELP and # TYPE declarations', str_contains($promOutput, '# HELP api_requests_total') && str_contains($promOutput, '# TYPE api_requests_total counter'));
assertTest('PrometheusExporter: correctly formats labeled metric lines', str_contains($promOutput, 'api_requests_total{code="200",method="GET"} 1'));
assertTest('PrometheusExporter: correctly formats gauge lines', str_contains($promOutput, 'active_connections 42'));

$jsonExporter = new JsonTelemetryExporter();
$jsonOutput = $jsonExporter->export($registry->getMetrics());
$decodedJson = json_decode($jsonOutput, true);
assertTest('JsonTelemetryExporter: generates valid JSON structure', is_array($decodedJson) && isset($decodedJson['metrics']) && count($decodedJson['metrics']) === 2);

$otlpExporter = new OtlpHttpExporter();
$otlpOutput = $otlpExporter->export($registry->getMetrics());
assertTest('OtlpHttpExporter: generates valid OTLP standard structure', str_contains($otlpOutput, 'resourceMetrics') && str_contains($otlpOutput, 'scopeMetrics'));

// -----------------------------------------------------------------------------
// 6. TelemetryManager & DI Integration
// -----------------------------------------------------------------------------
echo "\n--- 6. TelemetryManager & DI Integration ---\n";

$app = new Application(dirname(__DIR__));
$telemetryManager = new TelemetryManager($app, $registry);
$app->instance(TelemetryManager::class, $telemetryManager);
$app->instance('telemetry', $telemetryManager);
$app->instance('metrics', $telemetryManager);

// measure helper
$measureResult = $telemetryManager->measure('order_checkout', function () {
    return 100 * 2;
}, ['currency' => 'USD'], 'Checkout processing duration');

assertTest('TelemetryManager: measure() executes callable and creates histogram & counter', $measureResult === 200 && $telemetryManager->getMetric('order_checkout_duration_seconds') instanceof Histogram);

// Standard metrics helpers
$telemetryManager->recordHttpRequest('POST', '/api/orders', 201, 0.045);
$telemetryManager->recordQueueJob('SendEmailJob', 'success', 0.120);
$telemetryManager->recordDatabaseQuery('SELECT', 0.003, 'users');

assertTest('TelemetryManager: recordHttpRequest tracks http_requests_total', $telemetryManager->getMetric('http_requests_total') instanceof Counter);
assertTest('TelemetryManager: recordQueueJob tracks queue_jobs_total', $telemetryManager->getMetric('queue_jobs_total') instanceof Counter);
assertTest('TelemetryManager: recordDatabaseQuery tracks db_queries_total', $telemetryManager->getMetric('db_queries_total') instanceof Counter);

// -----------------------------------------------------------------------------
// 7. HTTP Controller & Middleware Tests
// -----------------------------------------------------------------------------
echo "\n--- 7. HTTP Controller & Middleware ---\n";

$metricsController = new PrometheusMetricsController($telemetryManager);
$metricsReq = new Request('GET', '/metrics');
$metricsResp = $metricsController->handle($metricsReq);

assertTest('PrometheusMetricsController: returns HTTP 200 with text/plain exposition format', $metricsResp->getStatusCode() === 200 && str_contains($metricsResp->getHeader('Content-Type') ?? '', 'text/plain'));
assertTest('PrometheusMetricsController: body contains recorded metrics', str_contains($metricsResp->getBody(), 'http_requests_total'));

// Middleware Test
$traceMiddleware = new TraceContextMiddleware($telemetryManager);
$incomingReq = new Request('GET', '/api/users/42', [], [], [
    'HTTP_TRACEPARENT' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
    'HTTP_BAGGAGE' => 'userId=alice,env=production',
]);

$midResp = $traceMiddleware->handle($incomingReq, function (Request $req) {
    return new Response('User data', 200);
});

assertTest('TraceContextMiddleware: propagates traceparent to response', $midResp->getHeader('traceparent') !== null && str_contains((string)$midResp->getHeader('traceparent'), '4bf92f3577b34da6a3ce929d0e0e4736'));
assertTest('TraceContextMiddleware: propagates baggage to response', $midResp->getHeader('baggage') !== null && str_contains((string)$midResp->getHeader('baggage'), 'userId=alice'));

// -----------------------------------------------------------------------------
// 8. Global Helpers
// -----------------------------------------------------------------------------
echo "\n--- 8. Global Helpers ---\n";

assertTest('Global Helper: telemetry() returns TelemetryManager', telemetry() instanceof TelemetryManager);
assertTest('Global Helper: metric_counter() returns Counter', metric_counter('helper_counter') instanceof Counter);
assertTest('Global Helper: metric_gauge() returns Gauge', metric_gauge('helper_gauge') instanceof Gauge);
assertTest('Global Helper: metric_histogram() returns Histogram', metric_histogram('helper_hist') instanceof Histogram);
assertTest('Global Helper: metric_measure() executes and measures', metric_measure('helper_op', fn() => 'measured') === 'measured');
assertTest('Global Helper: trace_context() retrieves active context', trace_context() instanceof TraceContext);
assertTest('Global Helper: baggage() sets and gets value', baggage('testKey', 'testVal') instanceof Baggage && baggage('testKey') === 'testVal');

// -----------------------------------------------------------------------------
// 9. Database Migration (SQLite)
// -----------------------------------------------------------------------------
echo "\n--- 9. Database Migration ---\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_20_000027_create_telemetry_metrics_tables.php';
$migration->up($pdo);

$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_telemetry_metrics'");
$hasTable = $stmt && $stmt->fetchColumn() === 'netfusion_telemetry_metrics';
$stmt->closeCursor();
unset($stmt);
assertTest('Database Migration: netfusion_telemetry_metrics table created', $hasTable);

$migration->down($pdo);
$stmtAfter = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_telemetry_metrics'");
$hasDropped = $stmtAfter && $stmtAfter->fetchColumn() === false;
$stmtAfter->closeCursor();
unset($stmtAfter);
assertTest('Database Migration: netfusion_telemetry_metrics table dropped cleanly on down()', $hasDropped);

echo "\n=======================================================\n";
echo "   Phase 47 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
