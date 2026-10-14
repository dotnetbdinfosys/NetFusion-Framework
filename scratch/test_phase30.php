<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase30.php
| Module    : Tests
| Purpose   : Automated verification suite for Phase 30 (Distributed Tracing & APM).
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Console\Kernel as ConsoleKernel;
use NetFusion\Core\Http\Middleware\TraceRequestMiddleware;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Tracing\ApmManager;
use NetFusion\Core\Tracing\Span;
use NetFusion\Core\Tracing\TraceContext;
use NetFusion\Core\Tracing\Tracer;

$basePath = realpath(__DIR__ . '/..');
$app = new Application($basePath);

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $label, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m: {$label}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL\033[0m: {$label}\n";
        if ($details !== '') {
            echo "         Detail: {$details}\n";
        }
    }
}

echo "\n============================================================\n";
echo "  NetFusion Verification Suite — Phase 30: Tracing & APM\n";
echo "============================================================\n";

// =========================================================================
//  SECTION 1: W3C TraceContext Specification
// =========================================================================
echo "\n\033[33m--- [Section 1] W3C TraceContext Specification ---\033[0m\n";

$ctx = TraceContext::create();
assertTest(strlen($ctx->traceId) === 32, 'create() generates 32-character hex trace ID');
assertTest(strlen($ctx->spanId) === 16, 'create() generates 16-character hex span ID');
assertTest($ctx->traceFlags === '01', 'create() sets default sampled flag 01');
assertTest($ctx->isSampled() === true, 'isSampled() returns true for 01 flag');

$traceparent = $ctx->toTraceparent();
assertTest(str_starts_with($traceparent, '00-'), 'toTraceparent() starts with W3C version 00-');
assertTest(substr_count($traceparent, '-') === 3, 'toTraceparent() contains exactly 3 hyphens');
assertTest($traceparent === "00-{$ctx->traceId}-{$ctx->spanId}-01", 'toTraceparent() matches exact 00-{trace_id}-{span_id}-01 spec');

// Parsing incoming traceparent
$remoteHeader = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
$parsed = TraceContext::fromTraceparent($remoteHeader);
assertTest($parsed !== null, 'fromTraceparent() parses valid W3C header');
assertTest($parsed->traceId === '4bf92f3577b34da6a3ce929d0e0e4736', 'Parsed traceId matches upstream trace ID');
assertTest($parsed->parentSpanId === '00f067aa0ba902b7', 'Parsed parentSpanId matches upstream span ID');
assertTest($parsed->spanId !== '00f067aa0ba902b7', 'Local span ID is newly generated for local execution layer');

// Rejecting invalid headers
assertTest(TraceContext::fromTraceparent('invalid-format') === null, 'Rejects invalid string');
assertTest(TraceContext::fromTraceparent('01-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01-extra') === null, 'Rejects 5 parts');
assertTest(TraceContext::fromTraceparent('00-shorttrace-00f067aa0ba902b7-01') === null, 'Rejects short trace ID');
assertTest(TraceContext::fromTraceparent('00-00000000000000000000000000000000-00f067aa0ba902b7-01') === null, 'Rejects all-zero trace ID');
assertTest(TraceContext::fromTraceparent('00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01') === null, 'Rejects all-zero parent ID');

// Child context derivation
$childCtx = $ctx->childSpan();
assertTest($childCtx->traceId === $ctx->traceId, 'childSpan() inherits exact trace ID');
assertTest($childCtx->parentSpanId === $ctx->spanId, 'childSpan() sets parentSpanId to previous spanId');
assertTest($childCtx->spanId !== $ctx->spanId, 'childSpan() generates distinct new span ID');

// =========================================================================
//  SECTION 2: Span Lifecycle & Attributes
// =========================================================================
echo "\n\033[33m--- [Section 2] Span Lifecycle & Attributes ---\033[0m\n";

$span = new Span('db.query', $ctx, ['db.system' => 'mysql']);
assertTest($span->getName() === 'db.query', 'Span name matches constructor argument');
assertTest($span->getAttribute('db.system') === 'mysql', 'Initial attribute db.system preserved');
assertTest($span->hasEnded() === false, 'Span has not ended yet');

usleep(5000); // 5ms
$span->setAttribute('db.statement', 'SELECT * FROM users WHERE id = ?');
$span->addEvent('query.cached', ['cache_hit' => false]);
$span->end();

assertTest($span->hasEnded() === true, 'Span hasEnded() is true after end()');
assertTest($span->durationMs() >= 4.0, 'Span durationMs() accurately reflects execution time (~5ms)');
assertTest(count($span->getEvents()) === 1, 'Event query.cached registered');
assertTest($span->getStatus() === Span::STATUS_OK, 'Default status is OK');

// Error recording on span
$errorSpan = new Span('payment.charge', $ctx);
$errorSpan->recordException(new RuntimeException('Gateway timeout', 504));
$errorSpan->end();

assertTest($errorSpan->getStatus() === Span::STATUS_ERROR, 'recordException() sets status to ERROR');
assertTest($errorSpan->getStatusDescription() === 'Gateway timeout', 'Error description matches exception message');
assertTest(count($errorSpan->getEvents()) === 1 && $errorSpan->getEvents()[0]['name'] === 'exception', 'Exception event logged on span');

$spanArray = $span->toArray();
assertTest(isset($spanArray['name'], $spanArray['trace_id'], $spanArray['span_id'], $spanArray['duration_ms']), 'toArray() exports valid span structure');

// =========================================================================
//  SECTION 3: Tracer Service & Hierarchy
// =========================================================================
echo "\n\033[33m--- [Section 3] Tracer Service & Hierarchy ---\033[0m\n";

$tracer = new Tracer();
$rootCtx = $tracer->startTrace();
assertTest($rootCtx instanceof TraceContext, 'startTrace() returns TraceContext');
assertTest($tracer->currentContext() === $rootCtx, 'currentContext() matches rootContext');

// Nested spans
$parentSpan = $tracer->startSpan('Controller:OrderController@store');
assertTest($tracer->getActiveSpan() === $parentSpan, 'Active span is parentSpan');
assertTest($parentSpan->getContext()->parentSpanId === null, 'Root span has null parentSpanId');

$childSpan1 = $tracer->startSpan('DB:insert_order');
assertTest($tracer->getActiveSpan() === $childSpan1, 'Active span pushed childSpan1');
assertTest($childSpan1->getContext()->parentSpanId === $parentSpan->getContext()->spanId, 'Child span points to parent span ID');

$tracer->endSpan($childSpan1);
assertTest($tracer->getActiveSpan() === $parentSpan, 'Popping childSpan1 restores parentSpan as active');

$childSpan2 = $tracer->startSpan('Event:OrderCreated');
$tracer->endSpan($childSpan2);
$tracer->endSpan($parentSpan);

assertTest($tracer->getActiveSpan() === null, 'All spans popped, active stack empty');
assertTest(count($tracer->getSpans()) === 3, 'Tracer collected all 3 finished spans');

// Measure helper
$measuredValue = $tracer->measure('heavy.calculation', function () {
    usleep(2000);
    return 42 * 2;
}, ['calc.type' => 'fibonacci']);

assertTest($measuredValue === 84, 'measure() returns callable result');
$spans = $tracer->getSpans();
$lastSpan = end($spans);
assertTest($lastSpan->getName() === 'heavy.calculation', 'measure() logged heavy.calculation span');
assertTest($lastSpan->getAttribute('calc.type') === 'fibonacci', 'measure() bound span attributes');

// Export & Flush
$exported = $tracer->export();
assertTest(count($exported) === 4, 'export() returns array of 4 spans');
$tracer->flush();
assertTest(count($tracer->getSpans()) === 0, 'flush() successfully clears all spans');

// =========================================================================
//  SECTION 4: APM Manager Metrics
// =========================================================================
echo "\n\033[33m--- [Section 4] APM Manager Metrics ---\033[0m\n";

$apm = new ApmManager();

// Record requests
$apm->recordRequest('GET', '/api/v1/users', 200, 10.5, 1024000);
$apm->recordRequest('GET', '/api/v1/users', 200, 12.0, 1024000);
$apm->recordRequest('POST', '/api/v1/users', 201, 25.0, 1500000);
$apm->recordRequest('GET', '/api/v1/unknown', 404, 5.0, 512000);
$apm->recordRequest('GET', '/api/v1/broken', 500, 80.0, 2048000);

// Record queries
$apm->recordQuery('SELECT * FROM users WHERE id = 1', 2.1);
$apm->recordQuery('SELECT * FROM orders WHERE total > 100', 15.4);
$apm->recordQuery('SELECT * FROM audit_logs WHERE payload LIKE "%term%"', 95.8); // Slow query > 50ms

// Record cache
$apm->recordCacheHit('user:1');
$apm->recordCacheHit('user:2');
$apm->recordCacheMiss('user:999');

// Record queue job
$apm->recordQueueJob('SendWelcomeEmail', 120.0, true);

$summary = $apm->getMetricsSummary();

assertTest($summary['requests']['total'] === 5, 'APM reports total 5 requests');
assertTest($summary['requests']['errors'] === 2, 'APM reports 2 error requests (404, 500)');
assertTest($summary['requests']['error_rate'] === 40.0, 'APM reports 40% error rate');
assertTest($summary['requests']['latency_avg'] > 0, 'APM calculates average latency');
assertTest($summary['requests']['latency_p95'] > 0, 'APM calculates p95 latency');

assertTest($summary['database']['total_queries'] === 3, 'APM reports 3 database queries');
assertTest($summary['database']['slow_queries'] === 1, 'APM accurately detects 1 slow query (> 50ms)');

$slowQueries = $apm->getSlowQueries(50.0);
assertTest(count($slowQueries) === 1, 'getSlowQueries(50.0) returns exactly 1 item');
assertTest($slowQueries[0]['duration_ms'] === 95.8, 'Slow query duration is 95.8ms');

assertTest($summary['cache']['hits'] === 2, 'Cache reports 2 hits');
assertTest($summary['cache']['misses'] === 1, 'Cache reports 1 miss');
assertTest($summary['cache']['hit_ratio'] === 66.67, 'Cache hit ratio is 66.67%');

assertTest($summary['memory']['peak_mb'] > 0, 'Memory peak_mb is positive');
assertTest($summary['queue']['total_jobs'] === 1, 'Queue reports 1 total job');

// =========================================================================
//  SECTION 5: TraceRequestMiddleware Integration
// =========================================================================
echo "\n\033[33m--- [Section 5] TraceRequestMiddleware Integration ---\033[0m\n";

$tracerInstance = new Tracer();
$apmInstance = new ApmManager();
$traceMiddleware = new TraceRequestMiddleware($tracerInstance, $apmInstance);

// Request without traceparent
$req1 = Request::create(uri: '/api/v1/products', method: 'GET');
$res1 = $traceMiddleware->handle($req1, function (Request $r) {
    return Response::json(['products' => ['Laptop', 'Mouse']]);
});

assertTest($res1->getStatusCode() === 200, 'Handled request returns 200 OK');
assertTest($res1->hasHeader('traceparent'), 'Response contains traceparent header');
assertTest($res1->hasHeader('X-Trace-Id'), 'Response contains X-Trace-Id header');
assertTest($res1->hasHeader('X-Response-Time'), 'Response contains X-Response-Time header');
assertTest($req1->getAttribute('trace_id') !== null, 'Request attribute trace_id injected');
assertTest($req1->getAttribute('span_id') !== null, 'Request attribute span_id injected');

$traceId1 = $res1->getHeader('X-Trace-Id');
assertTest(strlen($traceId1) === 32, 'X-Trace-Id is a 32-hex character ID');

// Request with incoming traceparent
$incomingTraceparent = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';
$req2 = Request::create(
    uri: '/api/v1/orders',
    method: 'POST',
    server: ['HTTP_TRACEPARENT' => $incomingTraceparent]
);

$res2 = $traceMiddleware->handle($req2, function (Request $r) {
    return Response::json(['order_id' => 12345], 201);
});

assertTest($res2->getStatusCode() === 201, 'Request with traceparent returns 201 Created');
assertTest($res2->getHeader('X-Trace-Id') === '0af7651916cd43dd8448eb211c80319c', 'Middleware preserved and propagated upstream trace ID');

$apmSummary = $apmInstance->getMetricsSummary();
assertTest($apmSummary['requests']['total'] === 2, 'Middleware recorded 2 requests into APM');

// =========================================================================
//  SECTION 6: Global Helpers
// =========================================================================
echo "\n\033[33m--- [Section 6] Global Helpers (trace, apm) ---\033[0m\n";

assertTest(function_exists('trace'), 'Global helper trace() exists');
assertTest(function_exists('apm'), 'Global helper apm() exists');
assertTest(trace() instanceof Tracer, 'trace() resolves Tracer singleton');
assertTest(apm() instanceof ApmManager, 'apm() resolves ApmManager singleton');

$tracedResult = trace('helper.test', function () {
    return 'hello from trace';
});
assertTest($tracedResult === 'hello from trace', 'trace(operation, callback) measures operation');

// =========================================================================
//  SECTION 7: CLI Command apm:summary
// =========================================================================
echo "\n\033[33m--- [Section 7] CLI Command apm:summary ---\033[0m\n";

/** @var ConsoleKernel $console */
$console = $app->make(ConsoleKernel::class);

// Seed some APM metrics in the container's APM instance
$globalApm = $app->make('apm');
$globalApm->recordRequest('GET', '/api/v1/dashboard', 200, 14.2);
$globalApm->recordRequest('GET', '/api/v1/orders', 200, 18.5);
$globalApm->recordQuery('SELECT * FROM users', 3.4);
$globalApm->recordQuery('SELECT * FROM large_transactions', 65.2);
$globalApm->recordCacheHit('dashboard:stats');

ob_start();
$exitCode = $console->handle(['netfusion', 'apm:summary', '--slow']);
$cliOutput = ob_get_clean();

assertTest($exitCode === 0, 'CLI apm:summary exits with code 0');
assertTest(str_contains($cliOutput, 'NetFusion APM'), 'CLI output contains APM header');
assertTest(str_contains($cliOutput, 'HTTP Request Latency & Throughput'), 'CLI output displays HTTP latency section');
assertTest(str_contains($cliOutput, 'Database Performance & Execution'), 'CLI output displays DB queries section');
assertTest(str_contains($cliOutput, 'Cache & Memory Consumption'), 'CLI output displays cache & memory section');
assertTest(str_contains($cliOutput, 'Slow Queries'), 'CLI output displays Slow Queries section');

// =========================================================================
//  SUMMARY
// =========================================================================
echo "\n============================================================\n";
echo "  Verification Results: Phase 30\n";
echo "  Total:  " . ($passed + $failed) . "\n";
echo "  Passed: \033[32m{$passed}\033[0m\n";
echo "  Failed: \033[31m{$failed}\033[0m\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
