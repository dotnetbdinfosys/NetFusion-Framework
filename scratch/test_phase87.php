<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Phase 87 Unit Tests
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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Edge\Cache\EdgeCache;
use NetFusion\Core\Edge\EdgeWorkerManager;
use NetFusion\Core\Edge\Enums\IsolateState;
use NetFusion\Core\Edge\Enums\WorkerType;
use NetFusion\Core\Edge\Exceptions\IsolateResourceExceededException;
use NetFusion\Core\Edge\Http\EdgeRequest;
use NetFusion\Core\Edge\Http\EdgeResponse;
use NetFusion\Core\Edge\Http\FetchEvent;
use NetFusion\Core\Edge\Isolate\ExecutionContext;
use NetFusion\Core\Edge\Isolate\Isolate;
use NetFusion\Core\Edge\Isolate\IsolatePool;
use NetFusion\Core\Edge\Worker\EdgeRouter;
use NetFusion\Core\Edge\Worker\WorkerRuntime;
use NetFusion\Core\Edge\Worker\WorkerScript;
use NetFusion\Core\Wasm\Binary\Leb128;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$testName}\n";
        $failed++;
    }
}

echo "Starting Phase 87 Tests...\n";

// --- Test 1: Isolate Sandbox Execution within Resource Bounds ---
$isolate = new Isolate(memoryLimitMb: 64, cpuTimeoutMs: 100);
$res = $isolate->execute(fn() => 40 + 2);

assertTest(
    $res === 42 &&
    $isolate->getState() === IsolateState::IDLE &&
    $isolate->getInvocationCount() === 1,
    "Test 1: Isolate executes callable safely and returns to IDLE state"
);

// --- Test 2: Isolate CPU Timeout Enforcement ---
$fastTimeoutIsolate = new Isolate(memoryLimitMb: 64, cpuTimeoutMs: 10);
$caughtCpuTimeout = false;

try {
    $fastTimeoutIsolate->execute(function () {
        usleep(25000); // 25ms > 10ms limit
        return 'too_slow';
    });
} catch (IsolateResourceExceededException $e) {
    $caughtCpuTimeout = true;
}

assertTest(
    $caughtCpuTimeout && $fastTimeoutIsolate->getState() === IsolateState::TRIPPED,
    "Test 2: Isolate trips into TRIPPED state and throws when CPU timeout is exceeded"
);

// --- Test 3: IsolatePool Pre-Warming & Reuse ---
$pool = new IsolatePool(minSpare: 3, maxCapacity: 10, defaultMemoryMb: 64, defaultCpuTimeoutMs: 100);
$statsBefore = $pool->stats();
$iso1 = $pool->acquire();
$statsMid = $pool->stats();
$pool->release($iso1);
$statsAfter = $pool->stats();

assertTest(
    $statsBefore['idle'] === 3 &&
    $statsMid['idle'] === 2 &&
    $statsAfter['idle'] === 3,
    "Test 3: IsolatePool pre-warms idle isolates and reuses them on release"
);

// --- Test 4: ExecutionContext & Asynchronous waitUntil Lifecycle ---
$ctx = new ExecutionContext();
$backgroundFired = false;
$ctx->waitUntil(function () use (&$backgroundFired) {
    $backgroundFired = true;
});

assertTest($ctx->getPendingCount() === 1 && !$backgroundFired, "Test 4: ExecutionContext queues background task");
$drained = $ctx->drain();
assertTest($drained === 1 && $backgroundFired && $ctx->getPendingCount() === 0, "Test 4b: ExecutionContext drains background tasks successfully");

// --- Test 5: EdgeRequest Parsing & Geo-Location Metadata ---
$req = new EdgeRequest(
    method: 'POST',
    url: 'https://edge.example.com/api/v1/auth/login?ref=partner&lang=bn',
    headers: ['Content-Type' => 'application/json', 'User-Agent' => 'NetFusion-EdgeClient'],
    body: '{"username":"admin","token":"xyz123"}',
    cf: ['country' => 'BD', 'city' => 'Dhaka', 'colo' => 'DHK', 'asn' => 13245]
);

assertTest(
    $req->getMethod() === 'POST' &&
    $req->getPath() === '/api/v1/auth/login' &&
    $req->getQueryParams()['ref'] === 'partner' &&
    $req->getHeader('content-type') === 'application/json' &&
    $req->json()['username'] === 'admin' &&
    $req->getCountry() === 'BD' &&
    $req->getCity() === 'Dhaka',
    "Test 5: EdgeRequest parses URL, query params, headers, JSON body, and edge geo metadata"
);

// --- Test 6: EdgeResponse Factory Helpers (JSON, HTML, Redirect) ---
$jsonRes = EdgeResponse::json(['message' => 'authenticated', 'status' => 200], 200, ['x-custom-edge' => 'val1']);
$htmlRes = EdgeResponse::html('<h1>Edge Welcome</h1>', 200);
$redRes = EdgeResponse::redirect('https://example.com/login', 302);

assertTest(
    $jsonRes->isOk() &&
    $jsonRes->jsonBody()['message'] === 'authenticated' &&
    $jsonRes->getHeader('x-custom-edge') === 'val1' &&
    str_contains($htmlRes->getHeader('content-type'), 'text/html') &&
    $redRes->getStatus() === 302 &&
    $redRes->getHeader('location') === 'https://example.com/login',
    "Test 6: EdgeResponse generates JSON, HTML, and redirect responses with proper headers"
);

// --- Test 7: FetchEvent Integration with respondWith ---
$fetchEvent = new FetchEvent($req, $ctx);
$fetchEvent->respondWith($jsonRes);

assertTest(
    $fetchEvent->getRequest()->getPath() === '/api/v1/auth/login' &&
    $fetchEvent->getResponse() === $jsonRes,
    "Test 7: FetchEvent binds request, response, and execution context"
);

// --- Test 8: EdgeCache Storage & TTL Match ---
$cache = new EdgeCache();
$cache->put($req, $jsonRes, 60, ['auth', 'users']);

$matched = $cache->match($req);
$missed = $cache->match('https://edge.example.com/non-existent');

assertTest(
    $matched !== null &&
    $matched->getHeader('cf-cache-status') === 'HIT' &&
    $matched->jsonBody()['message'] === 'authenticated' &&
    $missed === null,
    "Test 8: EdgeCache matches cached response and injects CF-Cache-Status: HIT"
);

// --- Test 9: EdgeCache Surrogate Key Tag Invalidation ---
$req2 = new EdgeRequest('GET', 'https://edge.example.com/profile/1');
$cache->put($req2, EdgeResponse::json(['user' => 'admin']), 300, ['users']);

assertTest($cache->count() === 2, "Test 9a: EdgeCache holds 2 entries before tag purge");
$purgedCount = $cache->purgeByTag('users');
assertTest(
    $purgedCount === 2 &&
    $cache->match($req) === null &&
    $cache->match($req2) === null,
    "Test 9b: EdgeCache purges all entries tagged with surrogate key 'users'"
);

// --- Test 10: WorkerScript Definition & Environment Bindings ---
$script = new WorkerScript(
    name: 'api-gateway',
    handler: function (FetchEvent $evt, array $env, ExecutionContext $ctx) {
        $envKey = $env['API_SECRET'] ?? 'none';
        return EdgeResponse::json(['auth' => 'granted', 'secret' => $envKey]);
    },
    type: WorkerType::PHP,
    env: ['API_SECRET' => 'shh-edge-secret']
);

assertTest(
    $script->getName() === 'api-gateway' &&
    $script->getType() === WorkerType::PHP &&
    $script->getEnvVar('API_SECRET') === 'shh-edge-secret',
    "Test 10: WorkerScript encapsulates name, type, handler, and environment variables"
);

// --- Test 11: WorkerRuntime Dispatch in Isolated Sandbox ---
$runtime = new WorkerRuntime($pool);
$bgExecuted = false;
$dispatchCtx = new ExecutionContext();
$dispatchCtx->waitUntil(function () use (&$bgExecuted) {
    $bgExecuted = true;
});

$dispatchedRes = $runtime->dispatch($script, $req, $dispatchCtx);

assertTest(
    $dispatchedRes->isOk() &&
    $dispatchedRes->jsonBody()['auth'] === 'granted' &&
    $dispatchedRes->jsonBody()['secret'] === 'shh-edge-secret' &&
    $bgExecuted === true,
    "Test 11: WorkerRuntime dispatches HTTP request into isolate and drains background tasks"
);

// --- Test 12: WorkerRuntime WebAssembly (Wasm) Module Sandbox Execution ---
function buildMinimalWasm(): string {
    $magic = "\x00\x61\x73\x6D";
    $version = "\x01\x00\x00\x00";

    // Type section: () -> i32
    $typePayload = "\x01\x60\x00\x01\x7F";
    $typeSec = "\x01" . Leb128::encodeUint(strlen($typePayload)) . $typePayload;

    // Function section: 1 func using type 0
    $funcPayload = "\x01\x00";
    $funcSec = "\x03" . Leb128::encodeUint(strlen($funcPayload)) . $funcPayload;

    // Export section: export "handle_request"
    $expPayload = "\x01" . Leb128::encodeUint(14) . "handle_request\x00\x00";
    $expSec = "\x07" . Leb128::encodeUint(strlen($expPayload)) . $expPayload;

    // Code section: i32.const 42 (0x41 0x2A), end (0x0B)
    $instructions = "\x41\x2A\x0B";
    $body = "\x00" . $instructions;
    $codePayload = "\x01" . Leb128::encodeUint(strlen($body)) . $body;
    $codeSec = "\x0A" . Leb128::encodeUint(strlen($codePayload)) . $codePayload;

    return $magic . $version . $typeSec . $funcSec . $expSec . $codeSec;
}

$wasmData = buildMinimalWasm();
$wasmScript = new WorkerScript('wasm-compute', $wasmData, WorkerType::WASM);
$wasmRes = $runtime->dispatch($wasmScript, new EdgeRequest('GET', 'https://edge.example.com/compute'));

assertTest(
    $wasmRes->isOk() &&
    $wasmRes->getHeader('x-edge-runtime') === 'wasm-v1' &&
    $wasmRes->getBody() === '42',
    "Test 12: WorkerRuntime parses and executes compiled WebAssembly (Wasm) binary module in isolate"
);

// --- Test 13: EdgeRouter Matching, HTTP Methods, and Geo-Fencing ---
$router = new EdgeRouter();
$router->addRoute('/api/v1/*', 'api-gateway', ['GET', 'POST']);
$router->addRoute('/api/bd-only/*', 'bd-service', ['GET'], 100, 'BD');
$router->addRoute('/compute/*', 'wasm-compute');

$matched1 = $router->match(new EdgeRequest('POST', 'https://edge.local/api/v1/auth'));
$matched2 = $router->match(new EdgeRequest('GET', 'https://edge.local/api/bd-only/catalog', [], '', ['country' => 'BD']));
$matched3Denied = $router->match(new EdgeRequest('GET', 'https://edge.local/api/bd-only/catalog', [], '', ['country' => 'US']));
$matched4 = $router->match(new EdgeRequest('GET', 'https://edge.local/compute/fibonacci'));

assertTest(
    $matched1 === 'api-gateway' &&
    $matched2 === 'bd-service' &&
    $matched3Denied === null &&
    $matched4 === 'wasm-compute',
    "Test 13: EdgeRouter matches paths, method filters, and enforces geo-fencing"
);

// --- Test 14: EdgeWorkerManager Facade Orchestration ---
$manager = new EdgeWorkerManager([], $pool, $cache, $router, $runtime);
$manager->register('api-gateway', $script->getHandler(), WorkerType::PHP, ['API_SECRET' => 'shh-edge-secret']);
$manager->register('wasm-compute', $wasmData, WorkerType::WASM);

$managedRes = $manager->handle(new EdgeRequest('POST', 'https://edge.local/api/v1/auth'));
$managerStats = $manager->stats();

assertTest(
    $managedRes->isOk() &&
    $managedRes->jsonBody()['auth'] === 'granted' &&
    $managerStats['workers_count'] === 2 &&
    $managerStats['routes_count'] === 3,
    "Test 14: EdgeWorkerManager routes request through cache and worker runtime seamlessly"
);

// --- Test 15: Global Helpers Registration & Direct Invocation ---
$app = new Application(dirname(__DIR__));
$app->singleton(EdgeWorkerManager::class, fn () => $manager);
$app->singleton('edge', fn () => $app->make(EdgeWorkerManager::class));

assertTest(
    function_exists('edge_worker') && function_exists('edge_invoke') && function_exists('edge_isolate'),
    "Test 15a: Global helpers edge_worker(), edge_invoke(), and edge_isolate() are declared"
);

$invokedHelper = edge_invoke('api-gateway', 'https://edge.local/api/v1/direct');
$isolatedCalc = edge_isolate(fn() => 100 * 2, 64, 50);

assertTest(
    $invokedHelper->isOk() &&
    $invokedHelper->jsonBody()['auth'] === 'granted' &&
    $isolatedCalc === 200,
    "Test 15b: edge_invoke() and edge_isolate() helpers execute successfully"
);

// --- Test 16: Database Migration Execution ---
$conn = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$migration = require __DIR__ . '/../database/migrations/2026_09_28_000070_create_edge_worker_tables.php';
$migration->up($conn);

$stmt = $conn->getPdo()->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('edge_workers', 'edge_routes', 'edge_worker_logs')");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

assertTest(
    count($tables) === 3 &&
    in_array('edge_workers', $tables) &&
    in_array('edge_routes', $tables) &&
    in_array('edge_worker_logs', $tables),
    "Test 16: Database migration creates edge_workers, edge_routes, and edge_worker_logs tables"
);

echo "\nPhase 87 Unit Tests Completed: {$passed} Passed, {$failed} Failed.\n";
exit($failed > 0 ? 1 : 0);
