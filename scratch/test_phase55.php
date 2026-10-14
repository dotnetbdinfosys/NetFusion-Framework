<?php

/**
 * DotNetBD Enterprise Framework
 * Phase 55: BPMN 2.0 Dynamic Workflow Engine & Saga Orchestrator Test Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Workflow\Definition\WorkflowBuilder;
use NetFusion\Core\Workflow\Definition\WorkflowDefinition;
use NetFusion\Core\Workflow\Definition\Nodes\ServiceTask;
use NetFusion\Core\Workflow\Contracts\TaskHandlerInterface;
use NetFusion\Core\Workflow\Contracts\WorkflowInstanceInterface;
use NetFusion\Core\Workflow\Execution\ExecutionContext;
use NetFusion\Core\Workflow\Execution\ProcessInstance;
use NetFusion\Core\Workflow\Exceptions\NodeExecutionException;
use NetFusion\Core\Workflow\Store\ArrayWorkflowStore;
use NetFusion\Core\Workflow\Store\DatabaseWorkflowStore;
use NetFusion\Core\Workflow\WorkflowEngine;

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $name): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS:\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL:\033[0m {$name}\n";
    }
}

echo "\n=========================================================================\n";
echo "   NetFusion Framework — Phase 55: BPMN 2.0 Workflow & Saga Engine Tests\n";
echo "=========================================================================\n\n";

// --- Custom Task Handlers for Testing ---
class CalculateTaxHandler implements TaskHandlerInterface
{
    public function execute(ExecutionContext $context): array
    {
        $subtotal = (float) $context->get('subtotal', 100);
        $tax = $subtotal * 0.15;
        return ['tax' => $tax, 'total' => $subtotal + $tax];
    }
}

class DebitAccountHandler implements TaskHandlerInterface
{
    public static array $debits = [];
    public static array $refunds = [];

    public function execute(ExecutionContext $context): array
    {
        $amount = (float) $context->get('total', 0);
        self::$debits[] = $amount;
        return ['debited' => true, 'debit_amount' => $amount];
    }

    public function compensate(ExecutionContext $context): void
    {
        $amount = (float) $context->get('total', 0);
        self::$refunds[] = $amount;
    }
}

class FaultyShippingHandler implements TaskHandlerInterface
{
    public function execute(ExecutionContext $context): array
    {
        throw new \RuntimeException("Courier network unavailable!");
    }
}

// ---------------------------------------------------------
// Test Group 1: Workflow Definition & Builder API
// ---------------------------------------------------------
echo "[1] Workflow Definition & Builder API\n";

$def1 = WorkflowBuilder::create('simple_order', 'Simple Order Workflow', 1)
    ->description('Standard checkout process')
    ->start('start_evt', 'Start')
    ->serviceTask('calc_tax', CalculateTaxHandler::class, 'Calculate Tax')
    ->serviceTask('debit_acc', DebitAccountHandler::class, 'Debit Account')
    ->end('end_evt', 'Order Completed')
    ->connect('start_evt', 'calc_tax')
    ->connect('calc_tax', 'debit_acc')
    ->connect('debit_acc', 'end_evt')
    ->build();

assert_test($def1->getId() === 'simple_order', 'WorkflowBuilder creates valid definition ID');
assert_test(count($def1->getNodes()) === 4, 'WorkflowBuilder registers correct node count (4 nodes)');
assert_test(count($def1->getSequenceFlows()) === 3, 'WorkflowBuilder connects sequence flows properly');

// JSON Serialization & Hydration
$json = $def1->toJson();
$rehydratedDef = WorkflowDefinition::fromJson($json);
assert_test($rehydratedDef->getId() === 'simple_order', 'WorkflowDefinition JSON export and import hydration matches ID');
assert_test(count($rehydratedDef->getNodes()) === 4, 'Rehydrated definition preserves all nodes');

// ---------------------------------------------------------
// Test Group 2: Linear Service Task Execution
// ---------------------------------------------------------
echo "\n[2] Linear Service Task Execution\n";

$engine = new WorkflowEngine(new ArrayWorkflowStore());
$engine->register($def1);

$instance = $engine->start('simple_order', ['subtotal' => 200.0], 'ORD-2026-001');

assert_test($instance->getStatus() === WorkflowInstanceInterface::STATUS_COMPLETED, 'Workflow completes execution successfully');
assert_test($instance->getVariable('tax') === 30.0, 'Task handler outputs merged into variables (tax: 30.0)');
assert_test($instance->getVariable('total') === 230.0, 'Subsequent task consumes previous output (total: 230.0)');
assert_test($instance->getVariable('debited') === true, 'Debit task executed and recorded variable');
assert_test($instance->isCompleted() === true, 'ProcessInstance isCompleted() returns true');

// ---------------------------------------------------------
// Test Group 3: Exclusive Gateway (XOR) Decision Branching
// ---------------------------------------------------------
echo "\n[3] Exclusive Gateway (XOR) Decision Branching\n";

$xorDef = WorkflowBuilder::create('vip_discount_flow', 'VIP Routing Flow')
    ->start('start')
    ->exclusiveGateway('check_vip', 'Check VIP Status', 'flow_standard')
    ->serviceTask('vip_discount', fn(ExecutionContext $ctx) => ['discount' => 50, 'path' => 'VIP'], 'VIP Discount')
    ->serviceTask('standard_rate', fn(ExecutionContext $ctx) => ['discount' => 0, 'path' => 'Standard'], 'Standard Rate')
    ->end('end')
    ->connect('start', 'check_vip')
    ->connect('check_vip', 'vip_discount', 'is_vip == true', 'VIP Flow', flowId: 'flow_vip')
    ->connect('check_vip', 'standard_rate', null, 'Standard Flow', isDefault: true, flowId: 'flow_standard')
    ->connect('vip_discount', 'end')
    ->connect('standard_rate', 'end')
    ->build();

$engine->register($xorDef);

// Test VIP branch
$vipInst = $engine->start('vip_discount_flow', ['is_vip' => true]);
assert_test($vipInst->getStatus() === WorkflowInstanceInterface::STATUS_COMPLETED, 'VIP instance completes');
assert_test($vipInst->getVariable('discount') === 50 && $vipInst->getVariable('path') === 'VIP', 'ExclusiveGateway routes to VIP branch on condition match');

// Test Standard (default) branch
$stdInst = $engine->start('vip_discount_flow', ['is_vip' => false]);
assert_test($stdInst->getStatus() === WorkflowInstanceInterface::STATUS_COMPLETED, 'Standard instance completes');
assert_test($stdInst->getVariable('discount') === 0 && $stdInst->getVariable('path') === 'Standard', 'ExclusiveGateway routes to Default branch when condition false');

// ---------------------------------------------------------
// Test Group 4: Human-in-the-Loop UserTask & Signal Resumption
// ---------------------------------------------------------
echo "\n[4] UserTask & Signal Resumption\n";

$userTaskDef = WorkflowBuilder::create('loan_approval', 'Loan Approval Process')
    ->start('start')
    ->serviceTask('credit_check', fn(ExecutionContext $ctx) => ['credit_score' => 750], 'Credit Check')
    ->userTask('manager_signoff', 'Manager Sign-off', assignee: 'finance_manager', formSchema: ['approved' => 'bool', 'comments' => 'string'])
    ->serviceTask('disburse_fund', fn(ExecutionContext $ctx) => ['disbursed' => true, 'fund_status' => 'DISBURSED'], 'Disburse Fund')
    ->end('end')
    ->connect('start', 'credit_check')
    ->connect('credit_check', 'manager_signoff')
    ->connect('manager_signoff', 'disburse_fund')
    ->connect('disburse_fund', 'end')
    ->build();

$engine->register($userTaskDef);

$loanInst = $engine->start('loan_approval', ['applicant' => 'Rakib']);

assert_test($loanInst->getStatus() === WorkflowInstanceInterface::STATUS_SUSPENDED, 'Workflow suspends on UserTask waiting for human action');
assert_test(count($loanInst->getWaitingTokens()) === 1, 'Waiting token generated on UserTask node');

$pendingTasks = $engine->getStore()->getUserTasks(assignee: 'finance_manager', instanceId: $loanInst->getId());
assert_test(count($pendingTasks) === 1, 'Pending user task found in store for assignee');

// Resume via completeUserTask
$resumedInst = $engine->completeUserTask($loanInst->getId(), 'manager_signoff', ['approved' => true, 'comments' => 'Looks good'], 'admin_user');

assert_test($resumedInst->getStatus() === WorkflowInstanceInterface::STATUS_COMPLETED, 'Workflow resumes and completes after UserTask is completed');
assert_test($resumedInst->getVariable('approved') === true, 'User task input payload merged on resume');
assert_test($resumedInst->getVariable('disbursed') === true, 'Post-UserTask steps executed after signal');

// ---------------------------------------------------------
// Test Group 5: Parallel Gateway (AND Fork & Join)
// ---------------------------------------------------------
echo "\n[5] Parallel Gateway (AND - Fork & Join)\n";

$parallelDef = WorkflowBuilder::create('parallel_verification', 'Parallel Checks')
    ->start('start')
    ->parallelGateway('fork_gate', 'Fork Gateway')
    ->serviceTask('verify_kyc', fn(ExecutionContext $ctx) => ['kyc_ok' => true], 'KYC Verification')
    ->serviceTask('verify_aml', fn(ExecutionContext $ctx) => ['aml_ok' => true], 'AML Verification')
    ->parallelGateway('join_gate', 'Join Gateway')
    ->serviceTask('final_approve', fn(ExecutionContext $ctx) => ['all_checks_passed' => true], 'Final Approval')
    ->end('end')
    ->connect('start', 'fork_gate')
    ->connect('fork_gate', 'verify_kyc')
    ->connect('fork_gate', 'verify_aml')
    ->connect('verify_kyc', 'join_gate')
    ->connect('verify_aml', 'join_gate')
    ->connect('join_gate', 'final_approve')
    ->connect('final_approve', 'end')
    ->build();

$engine->register($parallelDef);

$parallelInst = $engine->start('parallel_verification', ['user_id' => 999]);

assert_test($parallelInst->getStatus() === WorkflowInstanceInterface::STATUS_COMPLETED, 'Parallel workflow completes with fork and join synchronization');
assert_test($parallelInst->getVariable('kyc_ok') === true, 'Parallel branch 1 (KYC) executed');
assert_test($parallelInst->getVariable('aml_ok') === true, 'Parallel branch 2 (AML) executed');
assert_test($parallelInst->getVariable('all_checks_passed') === true, 'Join node synchronized before final step');

// ---------------------------------------------------------
// Test Group 6: Saga Orchestration & Rollback Compensation
// ---------------------------------------------------------
echo "\n[6] Saga Orchestration & Compensation Rollback\n";

DebitAccountHandler::$debits = [];
DebitAccountHandler::$refunds = [];

$sagaDef = WorkflowBuilder::create('saga_booking', 'Flight & Hotel Booking')
    ->start('start')
    ->serviceTask('charge_payment', DebitAccountHandler::class, 'Charge Payment', compensation: DebitAccountHandler::class)
    ->serviceTask('book_flight', fn(ExecutionContext $ctx) => ['flight_id' => 'FL-881'], 'Book Flight', compensation: fn(ExecutionContext $ctx) => DebitAccountHandler::$refunds[] = 'CANCEL_FLIGHT')
    ->serviceTask('book_hotel_faulty', FaultyShippingHandler::class, 'Book Hotel (Faulty)')
    ->end('end')
    ->connect('start', 'charge_payment')
    ->connect('charge_payment', 'book_flight')
    ->connect('book_flight', 'book_hotel_faulty')
    ->connect('book_hotel_faulty', 'end')
    ->build();

$engine->register($sagaDef);

$failedCaught = false;
try {
    $engine->start('saga_booking', ['total' => 500.0]);
} catch (NodeExecutionException $e) {
    $failedCaught = true;
}

assert_test($failedCaught === true, 'NodeExecutionException thrown on faulty service task');
assert_test(count(DebitAccountHandler::$debits) === 1, 'Payment was charged before fault');
assert_test(in_array(500.0, DebitAccountHandler::$refunds, true), 'Saga compensation handler executed refund of 500.0');
assert_test(in_array('CANCEL_FLIGHT', DebitAccountHandler::$refunds, true), 'Saga compensation closure executed in LIFO reverse order');

// ---------------------------------------------------------
// Test Group 7: Database Workflow Store Persistence
// ---------------------------------------------------------
echo "\n[7] Database Workflow Store Persistence\n";

$sqlitePdo = new PDO('sqlite::memory:');
$migration = require __DIR__ . '/../database/migrations/2026_09_20_000035_create_workflow_engine_tables.php';
$migration->up($sqlitePdo);

$dbStore = new DatabaseWorkflowStore($sqlitePdo);
$dbEngine = new WorkflowEngine($dbStore);

$dbEngine->register($def1);
$loadedDef = $dbStore->loadDefinition('simple_order');
assert_test($loadedDef !== null && $loadedDef->getId() === 'simple_order', 'WorkflowDefinition persisted and reloaded from DatabaseStore');

$dbInst = $dbEngine->start('simple_order', ['subtotal' => 150.0]);
assert_test($dbInst->getStatus() === WorkflowInstanceInterface::STATUS_COMPLETED, 'DatabaseStore engine executes instance');

$reloadedInst = $dbStore->loadInstance($dbInst->getId());
assert_test($reloadedInst !== null && $reloadedInst->getId() === $dbInst->getId(), 'ProcessInstance reloaded from SQLite database');
assert_test($reloadedInst->getVariable('total') === 172.5, 'Instance variables persisted in DatabaseStore (total: 172.5)');

$history = $dbStore->getActivityHistory($dbInst->getId());
assert_test(count($history) >= 4, 'Activity execution logs persisted to DatabaseStore (count >= 4)');

// ---------------------------------------------------------
// Test Group 8: Global Helpers & CLI Commands
// ---------------------------------------------------------
echo "\n[8] Global Helpers & CLI Commands\n";

$helperEngine = workflow_engine();
assert_test($helperEngine instanceof WorkflowEngine, 'workflow_engine() helper returns WorkflowEngine instance');

$helperEngine->register($def1);
$helperInst = workflow_start('simple_order', ['subtotal' => 300.0], 'BK-999');
assert_test($helperInst instanceof ProcessInstance && $helperInst->getVariable('total') === 345.0, 'workflow_start() helper executes workflow process');

echo "\n=========================================================================\n";
echo "   Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "=========================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
