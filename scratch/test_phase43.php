<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase43.php
| Module    : Tests
| Purpose   : Automated verification suite for Phase 43 (Microservices Service Mesh, RPC & Circuit Breaker).
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
use NetFusion\Core\Config\Config;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\RPC\CircuitBreaker\CircuitBreaker;
use NetFusion\Core\RPC\CircuitBreaker\CircuitBreakerOpenException;
use NetFusion\Core\RPC\CircuitBreaker\CircuitBreakerState;
use NetFusion\Core\RPC\Client\RpcClient;
use NetFusion\Core\RPC\Client\RpcProxy;
use NetFusion\Core\RPC\Contracts\CircuitBreakerInterface;
use NetFusion\Core\RPC\Contracts\LoadBalancerInterface;
use NetFusion\Core\RPC\Contracts\RpcClientInterface;
use NetFusion\Core\RPC\Contracts\RpcServerInterface;
use NetFusion\Core\RPC\Contracts\ServiceRegistryInterface;
use NetFusion\Core\RPC\LoadBalancer\LeastConnectionsLoadBalancer;
use NetFusion\Core\RPC\LoadBalancer\RandomLoadBalancer;
use NetFusion\Core\RPC\LoadBalancer\RoundRobinLoadBalancer;
use NetFusion\Core\RPC\Registry\Drivers\ArrayRegistryDriver;
use NetFusion\Core\RPC\Registry\Drivers\DatabaseRegistryDriver;
use NetFusion\Core\RPC\Registry\Drivers\FileRegistryDriver;
use NetFusion\Core\RPC\Registry\ServiceInstance;
use NetFusion\Core\RPC\Registry\ServiceRegistry;
use NetFusion\Core\RPC\RpcException;
use NetFusion\Core\RPC\RpcManager;
use NetFusion\Core\RPC\RpcRequest;
use NetFusion\Core\RPC\RpcResponse;
use NetFusion\Core\RPC\Server\RpcServer;

$basePath = realpath(__DIR__ . '/..');
$app = new Application($basePath);

$passed = 0;
$total = 0;

function t(bool $cond, string $desc): void
{
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$desc}\n";
    } else {
        echo "  \033[31m✖\033[0m {$desc}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 43 — Service Mesh, RPC & Circuit Breaker Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: RPC Request & Response Envelopes
// =============================================================================
echo "\033[36m[Section 1: RPC Request & Response Protocol Envelopes]\033[0m\n";

$req = RpcRequest::create(
    service: 'OrderService',
    method: 'calculateTotal',
    params: ['items' => [101, 102], 'taxRate' => 0.05],
    metadata: ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01']
);

t($req->getId() !== '', 'RpcRequest creates valid unique message ID');
t($req->service === 'OrderService', 'RpcRequest preserves service name');
t($req->method === 'calculateTotal', 'RpcRequest preserves method name');
t($req->params['taxRate'] === 0.05, 'RpcRequest stores params');
t(str_contains($req->toJson(), 'OrderService'), 'RpcRequest serializes to valid JSON');

$reqFromJson = RpcRequest::fromJson($req->toJson());
t($reqFromJson->getId() === $req->getId(), 'RpcRequest::fromJson reconstructs ID');
t($reqFromJson->params['items'] === [101, 102], 'RpcRequest::fromJson reconstructs params');

$res = RpcResponse::success($req->getId(), ['total' => 105.00, 'currency' => 'USD'], 1.45);
t($res->isSuccess() === true, 'RpcResponse::success sets isSuccess to true');
t($res->getResult()['total'] === 105.00, 'RpcResponse::getResult returns result payload');
t($res->getExecutionTimeMs() === 1.45, 'RpcResponse stores execution duration');

$resErr = RpcResponse::error($req->getId(), 'Item not found', RpcException::INVALID_PARAMS, ['item_id' => 999]);
t($resErr->isSuccess() === false, 'RpcResponse::error sets isSuccess to false');
t($resErr->getError()['code'] === RpcException::INVALID_PARAMS, 'RpcResponse::error stores error code');

try {
    $resErr->getResult();
    t(false, 'RpcResponse::getResult on error throws RpcException');
} catch (RpcException $e) {
    t($e->getCode() === RpcException::INVALID_PARAMS && str_contains($e->getMessage(), 'Item not found'), 'RpcResponse::getResult throws proper RpcException');
}

// =============================================================================
// Section 2: Circuit Breaker State Transitions & Fallbacks
// =============================================================================
echo "\033[36m[Section 2: Circuit Breaker State Transitions & Fallbacks]\033[0m\n";

$cb = new CircuitBreaker(
    serviceName: 'PaymentGateway',
    failureThreshold: 3,
    resetTimeoutSeconds: 0.1, // 100ms for fast testing
    halfOpenSuccessThreshold: 2
);

t($cb->getState() === CircuitBreakerState::CLOSED, 'CircuitBreaker initializes in CLOSED state');
t($cb->isAvailable() === true, 'CircuitBreaker in CLOSED state is available');

// 1. Successful execution
$resVal = $cb->execute(fn() => 'Charged $100');
t($resVal === 'Charged $100', 'CircuitBreaker executes successful closure');

// 2. Trip to OPEN on reaching failure threshold
try {
    $cb->execute(fn() => throw new RuntimeException('Connection refused 1'));
} catch (Throwable) {}
t($cb->getState() === CircuitBreakerState::CLOSED, 'Circuit remains CLOSED after 1 failure (< threshold 3)');

try {
    $cb->execute(fn() => throw new RuntimeException('Connection refused 2'));
} catch (Throwable) {}
try {
    $cb->execute(fn() => throw new RuntimeException('Connection refused 3'));
} catch (Throwable) {}

t($cb->getState() === CircuitBreakerState::OPEN, 'Circuit transitions to OPEN after 3 consecutive failures');
t($cb->isAvailable() === false, 'CircuitBreaker in OPEN state is NOT available');

// 3. Fast fail when OPEN
try {
    $cb->execute(fn() => 'Should not run');
    t(false, 'CircuitBreaker in OPEN state must throw CircuitBreakerOpenException');
} catch (CircuitBreakerOpenException $e) {
    t(str_contains($e->getMessage(), 'PaymentGateway') && str_contains($e->getMessage(), 'OPEN'), 'CircuitBreaker fast-fails with CircuitBreakerOpenException');
}

// 4. Fallback execution when OPEN
$fallbackRes = $cb->execute(
    action: fn() => 'Real Call',
    fallback: fn(Throwable $e) => 'Fallback Cached Response'
);
t($fallbackRes === 'Fallback Cached Response', 'CircuitBreaker executes fallback closure when circuit is OPEN');

// 5. Transition to HALF_OPEN after reset timeout
usleep(120000); // 120ms > 100ms timeout
t($cb->getState() === CircuitBreakerState::HALF_OPEN, 'Circuit transitions to HALF_OPEN after reset timeout');
t($cb->isAvailable() === true, 'Circuit in HALF_OPEN allows trial requests');

// 6. Trial 1 success in HALF_OPEN
$cb->execute(fn() => 'Trial 1 Success');
t($cb->getState() === CircuitBreakerState::HALF_OPEN, 'Circuit stays HALF_OPEN after 1 trial success (< halfOpenSuccessThreshold 2)');

// 7. Trial 2 success in HALF_OPEN -> Transitions to CLOSED
$cb->execute(fn() => 'Trial 2 Success');
t($cb->getState() === CircuitBreakerState::CLOSED, 'Circuit transitions back to CLOSED after 2 consecutive trial successes');

// 8. Test immediate trip to OPEN if HALF_OPEN trial fails
$cb->forceState(CircuitBreakerState::HALF_OPEN);
try {
    $cb->execute(fn() => throw new RuntimeException('Trial Failed'));
} catch (Throwable) {}
t($cb->getState() === CircuitBreakerState::OPEN, 'Circuit immediately trips back to OPEN if trial in HALF_OPEN fails');

// Reset
$cb->reset();
t($cb->getState() === CircuitBreakerState::CLOSED, 'CircuitBreaker::reset restores CLOSED state');

// =============================================================================
// Section 3: Service Discovery Registry Drivers (Array, Database, File)
// =============================================================================
echo "\033[36m[Section 3: Service Discovery Registry & Heartbeats]\033[0m\n";

// 1. ArrayRegistryDriver
$arrayReg = new ArrayRegistryDriver();
$node1 = ServiceInstance::create('AuthService', '10.0.0.1', 9501, ['region' => 'us-east']);
$node2 = ServiceInstance::create('AuthService', '10.0.0.2', 9501, ['region' => 'us-west']);
$node3 = ServiceInstance::create('PaymentService', '10.0.0.3', 9502);

$arrayReg->register($node1);
$arrayReg->register($node2);
$arrayReg->register($node3);

$discoveredAuth = $arrayReg->discover('AuthService');
t(count($discoveredAuth) === 2, 'ArrayRegistryDriver discovers all 2 active nodes for AuthService');
t($discoveredAuth[0]->host === '10.0.0.1' && $discoveredAuth[1]->host === '10.0.0.2', 'Discovered instances have correct hosts');

$arrayReg->deregister($node1->id);
t(count($arrayReg->discover('AuthService')) === 1, 'ArrayRegistryDriver deregisters node');

// 2. DatabaseRegistryDriver
$testDbFile = __DIR__ . '/test_rpc_mesh.sqlite';
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}
$conn = new Connection(['driver' => 'sqlite', 'database' => $testDbFile]);
$migration = require __DIR__ . '/../database/migrations/2026_09_20_000023_create_rpc_service_mesh_tables.php';
$migration->up($conn);

$dbReg = new DatabaseRegistryDriver($conn->getPdo());
$dbNode1 = ServiceInstance::create('InventoryService', '192.168.1.10', 9505);
$dbNode2 = ServiceInstance::create('InventoryService', '192.168.1.11', 9505);

$dbReg->register($dbNode1);
$dbReg->register($dbNode2);

$dbDiscovered = $dbReg->discover('InventoryService');
t(count($dbDiscovered) === 2, 'DatabaseRegistryDriver persists and discovers instances in database');
t($dbDiscovered[0]->serviceName === 'InventoryService', 'DatabaseRegistryDriver preserves serviceName');

$heartbeatOk = $dbReg->heartbeat($dbNode1->id);
t($heartbeatOk === true, 'DatabaseRegistryDriver updates node heartbeat timestamp');

$allServices = $dbReg->all();
t(isset($allServices['InventoryService']) && count($allServices['InventoryService']) === 2, 'DatabaseRegistryDriver::all returns grouped services');

$dbReg->deregister($dbNode1->id);
t(count($dbReg->discover('InventoryService')) === 1, 'DatabaseRegistryDriver deletes deregistered node');

// 3. FileRegistryDriver
$testFileRegPath = __DIR__ . '/test_file_registry.json';
if (file_exists($testFileRegPath)) {
    @unlink($testFileRegPath);
}
$fileReg = new FileRegistryDriver($testFileRegPath);
$fNode = ServiceInstance::create('NotificationService', '127.0.0.1', 9510);
$fileReg->register($fNode);
t(count($fileReg->discover('NotificationService')) === 1, 'FileRegistryDriver discovers registered instance');
t(file_exists($testFileRegPath), 'FileRegistryDriver created JSON file on disk');

// Clean test files
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}
if (file_exists($testFileRegPath)) {
    @unlink($testFileRegPath);
}

// =============================================================================
// Section 4: Service Mesh Load Balancers
// =============================================================================
echo "\033[36m[Section 4: Service Mesh Load Balancers]\033[0m\n";

$pool = [
    ServiceInstance::create('Gateway', '10.0.0.1', 8080, id: 'node-1'),
    ServiceInstance::create('Gateway', '10.0.0.2', 8080, id: 'node-2'),
    ServiceInstance::create('Gateway', '10.0.0.3', 8080, id: 'node-3'),
];

// 1. RoundRobin
$rr = new RoundRobinLoadBalancer();
$selected = [];
for ($i = 0; $i < 6; $i++) {
    $selected[] = $rr->select($pool, 'Gateway')->id;
}
t($selected === ['node-1', 'node-2', 'node-3', 'node-1', 'node-2', 'node-3'], 'RoundRobinLoadBalancer cycles evenly across nodes');

// 2. Random
$rnd = new RandomLoadBalancer();
$rndInst = $rnd->select($pool, 'Gateway');
t($rndInst instanceof ServiceInstance && in_array($rndInst->id, ['node-1', 'node-2', 'node-3'], true), 'RandomLoadBalancer selects valid instance');

// 3. LeastConnections
$least = new LeastConnectionsLoadBalancer();
$pool[0]->activeConnections = 10;
$pool[1]->activeConnections = 2; // Lowest
$pool[2]->activeConnections = 5;

$leastSelected = $least->select($pool, 'Gateway');
t($leastSelected->id === 'node-2', 'LeastConnectionsLoadBalancer chooses instance with lowest connection count');

// =============================================================================
// Section 5: RPC Server & Reflection Dispatching
// =============================================================================
echo "\033[36m[Section 5: RPC Server & In-Process Reflection Dispatch]\033[0m\n";

class TestCalculatorService
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function multiply(float $x, float $y): float
    {
        return $x * $y;
    }

    public function greet(string $name = 'Guest', string $title = 'User'): string
    {
        return "Hello, {$title} {$name}!";
    }

    public function fail(): void
    {
        throw new InvalidArgumentException('Division by zero error.');
    }
}

$server = new RpcServer($app);
$server->register('Calculator', new TestCalculatorService());

// 1. Valid positional call
$req1 = RpcRequest::create('Calculator', 'add', [15, 27]);
$rawRes1 = $server->handle($req1->toJson());
$res1 = RpcResponse::fromJson($rawRes1);
t($res1->isSuccess() === true, 'RpcServer handles valid positional call');
t($res1->getResult() === 42, 'RpcServer returns correct computed sum 42');

// 2. Valid named parameters call
$req2 = RpcRequest::create('Calculator', 'multiply', ['x' => 4.5, 'y' => 2.0]);
$rawRes2 = $server->handle($req2->toJson());
$res2 = RpcResponse::fromJson($rawRes2);
t((float) $res2->getResult() === 9.0, 'RpcServer handles named arguments correctly');

// 3. Default argument values
$req3 = RpcRequest::create('Calculator', 'greet', ['name' => 'Ashish']);
$rawRes3 = $server->handle($req3->toJson());
$res3 = RpcResponse::fromJson($rawRes3);
t($res3->getResult() === 'Hello, User Ashish!', 'RpcServer applies default method arguments');

// 4. Unknown service error
$req4 = RpcRequest::create('NonExistentService', 'test');
$rawRes4 = $server->handle($req4->toJson());
$res4 = RpcResponse::fromJson($rawRes4);
t($res4->isSuccess() === false && $res4->getError()['code'] === RpcException::METHOD_NOT_FOUND, 'RpcServer returns METHOD_NOT_FOUND for unknown service');

// 5. Unknown method error
$req5 = RpcRequest::create('Calculator', 'nonExistentMethod');
$rawRes5 = $server->handle($req5->toJson());
$res5 = RpcResponse::fromJson($rawRes5);
t($res5->isSuccess() === false && $res5->getError()['code'] === RpcException::METHOD_NOT_FOUND, 'RpcServer returns METHOD_NOT_FOUND for unknown method');

// 6. Exception handling
$req6 = RpcRequest::create('Calculator', 'fail');
$rawRes6 = $server->handle($req6->toJson());
$res6 = RpcResponse::fromJson($rawRes6);
t($res6->isSuccess() === false && str_contains($res6->getError()['message'], 'Division by zero'), 'RpcServer catches exception and returns structured error response');

// =============================================================================
// Section 6: RPC Client & Dynamic Proxy
// =============================================================================
echo "\033[36m[Section 6: RPC Client & Dynamic Proxy]\033[0m\n";

$registry = new ServiceRegistry();
$client = new RpcClient(registry: $registry, loadBalancer: new RoundRobinLoadBalancer());
$client->setLocalServer($server);

// 1. Direct call through client
$callRes = $client->call('Calculator', 'add', [100, 250]);
t($callRes === 350, 'RpcClient::call invokes local server with in-process optimization');

// 2. Fluent Proxy call
$proxy = $client->service('Calculator');
t($proxy instanceof RpcProxy, 'RpcClient::service returns RpcProxy instance');

$proxyRes = $proxy->add(50, 75);
t($proxyRes === 125, 'RpcProxy magic method __call executes remote method seamlessly');

$greetRes = $proxy->greet(name: 'Developer', title: 'Lead');
t($greetRes === 'Hello, Lead Developer!', 'RpcProxy resolves named arguments');

// 3. Fallback on client failure
$fallbackCall = $client->call(
    service: 'Calculator',
    method: 'fail',
    options: ['fallback' => fn($e) => 'Graceful Fallback Handled']
);
t($fallbackCall === 'Graceful Fallback Handled', 'RpcClient handles fallback gracefully on service error');

// =============================================================================
// Section 7: Global Helpers & RpcManager Facade
// =============================================================================
echo "\033[36m[Section 7: Global Helpers & Facade]\033[0m\n";

/** @var RpcManager $manager */
$manager = $app->make(RpcManager::class);
t($manager instanceof RpcManager, 'RpcManager resolved from service container');

$manager->register('Calculator', new TestCalculatorService());

t(function_exists('rpc'), 'Global helper rpc() exists');
t(rpc() instanceof RpcManager, 'rpc() returns RpcManager instance');

$proxyHelper = rpc('Calculator');
t($proxyHelper instanceof RpcProxy, 'rpc("Calculator") returns RpcProxy');
t($proxyHelper->add(200, 300) === 500, 'rpc("Calculator")->add() executes successfully');

t(function_exists('circuit_breaker'), 'Global helper circuit_breaker() exists');
t(circuit_breaker('test_cb') instanceof CircuitBreaker, 'circuit_breaker("test_cb") returns CircuitBreaker instance');

$cbHelperExec = circuit_breaker('math_ops', fn() => 10 * 10);
t($cbHelperExec === 100, 'circuit_breaker() helper executes action directly with protection');

echo "\n=================================================================\n";
echo "  Results: {$passed}/{$total} Passed (" . round(($passed / $total) * 100) . "%)\n";
echo "=================================================================\n\n";

exit($total - $passed);
