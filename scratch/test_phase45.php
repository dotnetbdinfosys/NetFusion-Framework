<?php

/**
 * NetFusion Framework - Phase 45 Comprehensive Test Suite
 * Enterprise Saga Orchestrator, Distributed Transactions & Compensation Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Console\Kernel;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Saga\Contracts\SagaInterface;
use NetFusion\Core\Saga\Contracts\SagaStepInterface;
use NetFusion\Core\Saga\Contracts\SagaStoreInterface;
use NetFusion\Core\Saga\SagaBuilder;
use NetFusion\Core\Saga\SagaContext;
use NetFusion\Core\Saga\SagaCoordinator;
use NetFusion\Core\Saga\SagaException;
use NetFusion\Core\Saga\SagaInstance;
use NetFusion\Core\Saga\SagaManager;
use NetFusion\Core\Saga\SagaState;
use NetFusion\Core\Saga\SagaStep;
use NetFusion\Core\Saga\SagaStepResult;
use NetFusion\Core\Saga\Store\ArraySagaStore;
use NetFusion\Core\Saga\Store\DatabaseSagaStore;

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($detail ? " - {$detail}" : '') . "\n";
    }
}

echo "\n=======================================================\n";
echo " NETFUSION PHASE 45 TEST SUITE: SAGA ORCHESTRATOR & 2PC\n";
echo "=======================================================\n\n";

// -----------------------------------------------------------------------------
// 1. SagaState, SagaContext & Value Objects
// -----------------------------------------------------------------------------
echo "1. Testing Saga State & Context...\n";

assert_test("SagaState::PENDING is not final", !SagaState::PENDING->isFinal());
assert_test("SagaState::COMPLETED is final and successful", SagaState::COMPLETED->isFinal() && SagaState::COMPLETED->isSuccessful());
assert_test("SagaState::COMPENSATED is final and rolled back", SagaState::COMPENSATED->isFinal() && SagaState::COMPENSATED->isRolledBack());
assert_test("SagaState::FAILED is final", SagaState::FAILED->isFinal() && !SagaState::FAILED->isSuccessful());

$ctx = new SagaContext(['order_id' => 'ORD-100', 'amount' => 250.0]);
assert_test("SagaContext get property", $ctx->get('order_id') === 'ORD-100');
assert_test("SagaContext array access", $ctx['amount'] === 250.0);
$ctx->set('status', 'processing');
assert_test("SagaContext set property", $ctx->get('status') === 'processing');
$ctx->merge(['items' => [1, 2, 3], 'tax' => 12.5]);
assert_test("SagaContext merge", count($ctx->get('items')) === 3 && $ctx->get('tax') === 12.5);
assert_test("SagaContext has key", $ctx->has('tax') && !$ctx->has('nonexistent'));
$ctx->remove('tax');
assert_test("SagaContext remove key", !$ctx->has('tax'));


// -----------------------------------------------------------------------------
// 2. SagaStep & SagaBuilder Definition
// -----------------------------------------------------------------------------
echo "\n2. Testing SagaStep & SagaBuilder...\n";

$step1 = new SagaStep(
    name: 'reserve_inventory',
    action: fn(SagaContext $c) => $c->set('inventory_reserved', true),
    compensation: fn(SagaContext $c) => $c->set('inventory_reserved', false),
    retries: 2,
    timeout: 5.0
);
assert_test("SagaStep properties", $step1->getName() === 'reserve_inventory' && $step1->getRetries() === 2 && $step1->getTimeout() === 5.0);

$builder = SagaBuilder::make('checkout_order');
$builder->step('reserve_stock', fn($c) => 'stock_ok', fn($c) => 'stock_released')->retries(1);
$builder->step('process_payment', fn($c) => 'paid_ok', fn($c) => 'refunded_ok');
$builder->step('schedule_shipping', fn($c) => 'shipping_ok', fn($c) => 'shipping_cancelled');

assert_test("SagaBuilder step count", count($builder->getSteps()) === 3);
assert_test("SagaBuilder first step retries", $builder->getSteps()[0]->getRetries() === 1);


// -----------------------------------------------------------------------------
// 3. SagaCoordinator - Happy Path (Successful Execution)
// -----------------------------------------------------------------------------
echo "\n3. Testing SagaCoordinator (Happy Path)...\n";

$executedSteps = [];
$startedHookCalled = false;
$completedHookCalled = false;

$happySaga = SagaBuilder::make('order_happy_path')
    ->whenStarting(function (SagaContext $c) use (&$startedHookCalled) {
        $startedHookCalled = true;
    })
    ->whenCompleted(function (SagaContext $c) use (&$completedHookCalled) {
        $completedHookCalled = true;
    });

$happySaga->step('validate_order', function (SagaContext $c) use (&$executedSteps) {
    $executedSteps[] = 'validate_order';
    $c->set('validated', true);
    return ['status' => 'valid'];
});

$happySaga->step('charge_card', function (SagaContext $c) use (&$executedSteps) {
    $executedSteps[] = 'charge_card';
    $c->set('transaction_id', 'TX-777');
    return ['tx' => 'TX-777'];
}, function (SagaContext $c) {
    $c->set('refunded', true);
});

$happySaga->step('send_email', function (SagaContext $c) use (&$executedSteps) {
    $executedSteps[] = 'send_email';
    $c->set('email_sent', true);
});

$store = new ArraySagaStore();
$coordinator = new SagaCoordinator($store);

$instance = SagaInstance::create('order_happy_path', ['order_id' => 'ORD-1']);
$resInstance = $coordinator->run($happySaga, $instance);

assert_test("Happy path saga state is COMPLETED", $resInstance->state === SagaState::COMPLETED);
assert_test("Happy path isSuccess() is true", $resInstance->isSuccess());
assert_test("All 3 forward steps executed", count($executedSteps) === 3 && $executedSteps === ['validate_order', 'charge_card', 'send_email']);
assert_test("Context mutated by steps", $resInstance->context->get('transaction_id') === 'TX-777' && $resInstance->context->get('email_sent') === true);
assert_test("Lifecycle onStart hook triggered", $startedHookCalled);
assert_test("Lifecycle onComplete hook triggered", $completedHookCalled);
assert_test("Step results stored in instance", count($resInstance->stepResults) === 3 && $resInstance->getStepResult('charge_card')->success);


// -----------------------------------------------------------------------------
// 4. SagaCoordinator - Failure & Backward Compensation Rollback
// -----------------------------------------------------------------------------
echo "\n4. Testing SagaCoordinator (Failure & Reverse Compensation)...\n";

$forwardLog = [];
$compensateLog = [];
$compensatingHookCalled = false;
$compensatedHookCalled = false;

$failSaga = SagaBuilder::make('order_failure_rollback')
    ->whenCompensating(function (SagaContext $c, Throwable $e) use (&$compensatingHookCalled) {
        $compensatingHookCalled = true;
    })
    ->whenCompensated(function (SagaContext $c) use (&$compensatedHookCalled) {
        $compensatedHookCalled = true;
    });

// Step 1: Inventory
$failSaga->step('reserve_inventory', function (SagaContext $c) use (&$forwardLog) {
    $forwardLog[] = 'reserve_inventory';
    $c->set('inventory_hold', true);
    return 'held';
}, function (SagaContext $c) use (&$compensateLog) {
    $compensateLog[] = 'compensate_inventory';
    $c->set('inventory_hold', false);
});

// Step 2: Payment Gateway
$failSaga->step('charge_payment', function (SagaContext $c) use (&$forwardLog) {
    $forwardLog[] = 'charge_payment';
    $c->set('charged', true);
    return 'charged_ok';
}, function (SagaContext $c) use (&$compensateLog) {
    $compensateLog[] = 'compensate_payment';
    $c->set('charged', false);
    $c->set('refund_issued', true);
});

// Step 3: Warehouse Shipping (Will Fail)
$failSaga->step('ship_goods', function (SagaContext $c) use (&$forwardLog) {
    $forwardLog[] = 'ship_goods';
    throw new RuntimeException("Warehouse connection timeout: out of stock");
}, function (SagaContext $c) use (&$compensateLog) {
    $compensateLog[] = 'compensate_shipping';
});

$failInstance = SagaInstance::create('order_failure_rollback', ['order_id' => 'ORD-2']);
$resFailInstance = $coordinator->run($failSaga, $failInstance);

assert_test("Failing saga state is COMPENSATED", $resFailInstance->state === SagaState::COMPENSATED);
assert_test("Failing saga isCompensated() is true", $resFailInstance->isCompensated());
assert_test("Forward steps executed up to step 3", $forwardLog === ['reserve_inventory', 'charge_payment', 'ship_goods']);
assert_test("Compensating actions executed in EXACT REVERSE order (Step 2 -> Step 1)", $compensateLog === ['compensate_payment', 'compensate_inventory']);
assert_test("Compensating actions reverted state in context", $resFailInstance->context->get('inventory_hold') === false && $resFailInstance->context->get('refund_issued') === true);
assert_test("Lifecycle onCompensating hook triggered", $compensatingHookCalled);
assert_test("Lifecycle onCompensated hook triggered", $compensatedHookCalled);
assert_test("Step 1 and Step 2 marked compensated in step results", $resFailInstance->getStepResult('reserve_inventory')->compensated && $resFailInstance->getStepResult('charge_payment')->compensated);
assert_test("Step 3 recorded error message", str_contains($resFailInstance->getStepResult('ship_goods')->error, 'Warehouse connection timeout'));


// -----------------------------------------------------------------------------
// 5. Retry Mechanism for Transient Step Failures
// -----------------------------------------------------------------------------
echo "\n5. Testing Transient Error Retry Mechanism...\n";

$attemptCount = 0;
$retrySaga = SagaBuilder::make('retry_test');
$retrySaga->step('flaky_api_call', function (SagaContext $c) use (&$attemptCount) {
    $attemptCount++;
    if ($attemptCount < 3) {
        throw new RuntimeException("503 Service Unavailable");
    }
    return "API response success";
})->retries(3, delayMs: 10.0);

$retryInstance = SagaInstance::create('retry_test');
$resRetry = $coordinator->run($retrySaga, $retryInstance);

assert_test("Step with retries succeeded on 3rd attempt", $resRetry->isSuccess() && $attemptCount === 3);
assert_test("Step result recorded success after retries", $resRetry->getStepResult('flaky_api_call')->success);


// -----------------------------------------------------------------------------
// 6. DatabaseSagaStore Persistence
// -----------------------------------------------------------------------------
echo "\n6. Testing DatabaseSagaStore Persistence & Querying...\n";

$dbPath = sys_get_temp_dir() . '/netfusion_saga_test_' . uniqid() . '.sqlite';
$conn = new Connection(['driver' => 'sqlite', 'database' => $dbPath]);

// Run migration
$migration = require __DIR__ . '/../database/migrations/2026_09_20_000025_create_saga_orchestrator_tables.php';
$migration->up($conn);

$dbStore = new DatabaseSagaStore($conn->getPdo());

// Save instance
$dbStore->save($resInstance);
$dbStore->save($resFailInstance);

assert_test("DatabaseSagaStore all() count is 2", count($dbStore->all()) === 2);

$foundHappy = $dbStore->find($resInstance->id);
assert_test("DatabaseSagaStore finds instance by ID", $foundHappy !== null && $foundHappy->id === $resInstance->id);
assert_test("DatabaseSagaStore preserves state COMPLETED", $foundHappy->state === SagaState::COMPLETED);
assert_test("DatabaseSagaStore preserves context data", $foundHappy->context->get('transaction_id') === 'TX-777');
assert_test("DatabaseSagaStore restores step results", count($foundHappy->stepResults) === 3 && $foundHappy->getStepResult('validate_order')->success);

$foundFail = $dbStore->find($resFailInstance->id);
assert_test("DatabaseSagaStore preserves COMPENSATED state", $foundFail->state === SagaState::COMPENSATED);
assert_test("DatabaseSagaStore preserves compensation flags on steps", $foundFail->getStepResult('charge_payment')->compensated);

// Filter by status
$completedList = $dbStore->all(['status' => 'COMPLETED']);
assert_test("DatabaseSagaStore filter by status COMPLETED", count($completedList) === 1 && $completedList[0]->id === $resInstance->id);

$dbStore->delete($resInstance->id);
assert_test("DatabaseSagaStore delete removes instance", $dbStore->find($resInstance->id) === null && count($dbStore->all()) === 1);


// -----------------------------------------------------------------------------
// 7. SagaManager, DI Container & Global Helpers
// -----------------------------------------------------------------------------
echo "\n7. Testing SagaManager & Global Helpers...\n";

$app = new Application(dirname(__DIR__));
$app->instance(\NetFusion\Core\Database\DatabaseManager::class, new class($conn) {
    public function __construct(protected $conn) {}
    public function connection() { return $this->conn; }
});

/** @var SagaManager $sagaManager */
$sagaManager = $app->make(SagaManager::class);

assert_test("SagaManager singleton resolved from DI container", $sagaManager instanceof SagaManager);
assert_test("SagaManager default store is ArraySagaStore", $sagaManager->store() instanceof ArraySagaStore);
assert_test("SagaManager database store resolution", $sagaManager->store('database') instanceof DatabaseSagaStore);

// Define Saga via manager
$sagaManager->define('subscription_flow', function (SagaBuilder $b) {
    $b->step('provision_tenant', fn($c) => 'tenant_ok', fn($c) => 'tenant_removed');
    $b->step('setup_billing', fn($c) => 'billing_ok', fn($c) => 'billing_refunded');
});

assert_test("SagaManager has('subscription_flow') is true", $sagaManager->has('subscription_flow'));

$subInst = $sagaManager->run('subscription_flow', ['tenant' => 'acme_corp']);
assert_test("SagaManager::run executes defined Saga", $subInst->isSuccess() && count($subInst->stepResults) === 2);

// Global helpers test
assert_test("saga() helper returns SagaManager", saga() instanceof SagaManager);
assert_test("saga('subscription_flow') helper returns SagaInterface", saga('subscription_flow') instanceof SagaInterface);

$helperInst = saga_start('subscription_flow', ['tenant' => 'globex']);
assert_test("saga_start() helper executes saga", $helperInst instanceof SagaInstance && $helperInst->isSuccess());


// -----------------------------------------------------------------------------
// 8. Console CLI Commands (saga:list, saga:show, saga:retry, saga:compensate)
// -----------------------------------------------------------------------------
echo "\n8. Testing Saga Console CLI Commands...\n";

$kernel = $app->make(Kernel::class);

// Seed definitions and in-memory store
$app->make(SagaManager::class)->define('order_happy_path', $happySaga);
$app->make(SagaManager::class)->define('order_failure_rollback', $failSaga);
$app->make(SagaManager::class)->store()->save($resInstance);
$app->make(SagaManager::class)->store()->save($resFailInstance);

// Test saga:list
ob_start();
$exitList = $kernel->handle(['netfusion', 'saga:list']);
$listOutput = ob_get_clean();
assert_test("CLI saga:list exit code 0", $exitList === 0);
assert_test("CLI saga:list output contains table header", str_contains($listOutput, 'Distributed Saga Instances'));

// Test saga:show
ob_start();
$exitShow = $kernel->handle(['netfusion', 'saga:show', $resInstance->id]);
$showOutput = ob_get_clean();
assert_test("CLI saga:show exit code 0", $exitShow === 0);
assert_test("CLI saga:show output contains execution details", str_contains($showOutput, 'Saga Execution Details') && str_contains($showOutput, 'validate_order'));

// Test saga:compensate
ob_start();
$exitComp = $kernel->handle(['netfusion', 'saga:compensate', $resInstance->id]);
$compOutput = ob_get_clean();
assert_test("CLI saga:compensate exit code 0", $exitComp === 0);
assert_test("CLI saga:compensate output confirms rollback", str_contains($compOutput, 'successfully compensated'));

// Test saga:retry
ob_start();
$exitRetry = $kernel->handle(['netfusion', 'saga:retry', $resInstance->id]);
$retryOutput = ob_get_clean();
assert_test("CLI saga:retry exit code 0", $exitRetry === 0);
assert_test("CLI saga:retry output confirms completion", str_contains($retryOutput, 'successfully completed after retry'));


// -----------------------------------------------------------------------------
// Clean up
// -----------------------------------------------------------------------------
if (file_exists($dbPath)) {
    @unlink($dbPath);
}

echo "\n=======================================================\n";
echo " PHASE 45 TESTS FINISHED\n";
echo " Passed: {$passed} | Failed: {$failed}\n";
echo "=======================================================\n\n";

exit($failed > 0 ? 1 : 0);
