<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 37 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase37.php
| Module    : Workflow
| Purpose   : Comprehensive tests for State Machines, Workflow Automation,
|             and Multi-Stage Approval Pipelines.
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
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Database\Model;
use NetFusion\Core\Workflow\ApprovalPipeline;
use NetFusion\Core\Workflow\State;
use NetFusion\Core\Workflow\StateMachine;
use NetFusion\Core\Workflow\Traits\HasWorkflow;
use NetFusion\Core\Workflow\Transition;
use NetFusion\Core\Workflow\WorkflowManager;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

$sqlitePath = __DIR__ . '/test_phase37.sqlite';
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}
touch($sqlitePath);

$app->make(Config::class)->set('database.default', 'sqlite');
$app->make(Config::class)->set('database.connections.sqlite.database', $sqlitePath);

/** @var DatabaseManager $db */
$db   = $app->make(DatabaseManager::class);
$conn = $db->connection('sqlite');

// Run migrations
$migration = require __DIR__ . '/../database/migrations/2026_09_18_000019_create_workflow_history_tables.php';
$migration->up($conn);

$passed = 0;
$failed = 0;
$total  = 0;

function t(bool $cond, string $name): void
{
    global $passed, $failed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖\033[0m {$name}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 37 — State Machine & Approval Pipeline Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: State & Transition Primitives
// =============================================================================
echo "\033[36m[Section 1: State & Transition Primitives]\033[0m\n";

$sDraft = State::make('draft', 'Draft State')->initial()->meta(['color' => 'gray']);
t($sDraft->getName() === 'draft', 'State::getName() returns "draft"');
t($sDraft->getLabel() === 'Draft State', 'State::getLabel() returns "Draft State"');
t($sDraft->isInitial() === true, 'State::isInitial() returns true');
t($sDraft->getMetadata()['color'] === 'gray', 'State::getMetadata() returns custom metadata');

$sPaid = State::make('paid')->final();
t($sPaid->isFinal() === true, 'State::isFinal() returns true');

$actionTriggered = false;
$trPay = Transition::make('pay', 'issued', 'paid')
    ->guard(fn($subject, $ctx) => ($ctx['amount'] ?? 0) > 0)
    ->action(function () use (&$actionTriggered) {
        $actionTriggered = true;
    });

$dummySubject = (object)['status' => 'issued'];
t($trPay->getName() === 'pay', 'Transition::getName() returns "pay"');
t($trPay->getFromStates() === ['issued'], 'Transition::getFromStates() returns ["issued"]');
t($trPay->getToState() === 'paid', 'Transition::getToState() returns "paid"');
t($trPay->can($dummySubject, ['amount' => 100]) === true, 'Transition guard passes with valid amount');
t($trPay->can($dummySubject, ['amount' => 0]) === false, 'Transition guard blocks with zero amount');

$trPay->execute($dummySubject);
t($actionTriggered === true, 'Transition::execute() triggers registered action hooks');

// =============================================================================
// Section 2: Finite State Machine Engine
// =============================================================================
echo "\n\033[36m[Section 2: Finite State Machine Engine]\033[0m\n";

$sm = new StateMachine('invoice', 'status');
$sm->addState(State::make('draft')->initial())
   ->addState(State::make('issued'))
   ->addState(State::make('paid'))
   ->addState(State::make('cancelled')->final());

$sm->addTransition(Transition::make('issue', 'draft', 'issued'))
   ->addTransition(Transition::make('pay', 'issued', 'paid'))
   ->addTransition(Transition::make('cancel', ['draft', 'issued'], 'cancelled'));

$testOrder = (object)['id' => 10, 'status' => 'draft'];

t($sm->getCurrentState($testOrder) === 'draft', 'StateMachine::getCurrentState() resolves subject state');
t($sm->can($testOrder, 'issue') === true, 'StateMachine::can("issue") allowed from "draft"');
t($sm->can($testOrder, 'pay') === false, 'StateMachine::can("pay") blocked from "draft"');

// Available transitions
$avail = $sm->getAvailableTransitions($testOrder);
t(isset($avail['issue'], $avail['cancel']) && !isset($avail['pay']), 'StateMachine::getAvailableTransitions() returns valid transitions');

// Apply transition draft -> issued
$sm->apply($testOrder, 'issue', ['actor_id' => 1, 'comment' => 'Issued by Billing']);
t($testOrder->status === 'issued', 'StateMachine::apply("issue") updates subject state to "issued"');

// Apply transition issued -> paid
$sm->apply($testOrder, 'pay', ['actor_id' => 2, 'comment' => 'Paid via Gateway']);
t($testOrder->status === 'paid', 'StateMachine::apply("pay") updates subject state to "paid"');

// History verification from database
$history = $sm->getHistory($testOrder);
t(count($history) === 2, 'StateMachine persists 2 transitions in workflow_histories table');
t($history[0]['from_state'] === 'draft' && $history[0]['to_state'] === 'issued', 'History record 1 matches draft -> issued');
t($history[1]['from_state'] === 'issued' && $history[1]['to_state'] === 'paid', 'History record 2 matches issued -> paid');
t($history[1]['comments'] === 'Paid via Gateway', 'History record contains audit comments');

// =============================================================================
// Section 3: Multi-Stage Approval Pipeline Engine
// =============================================================================
echo "\n\033[36m[Section 3: Multi-Stage Approval Pipeline Engine]\033[0m\n";

$pipeline = new ApprovalPipeline('expense_claim');
$pipeline->addStage('Manager Approval', approverId: 101)
         ->addStage('Finance Approval', approverId: 102)
         ->addStage('Executive Approval', approverId: 103);

t(count($pipeline->getStages()) === 3, 'ApprovalPipeline configured with 3 stages');

$expense = (object)['id' => 501, 'title' => 'Conference Trip Expense'];

// Submit to pipeline
t($pipeline->getStatus($expense) === 'not_submitted', 'Approval status is "not_submitted" prior to submission');
$pipeline->submit($expense);
t($pipeline->getStatus($expense) === 'pending', 'Approval status is "pending" after submission');

$req = $pipeline->getCurrentRequest($expense);
t($req['stage_name'] === 'Manager Approval' && (int)$req['approver_id'] === 101, 'Current approval request assigned to Stage 1 (Manager)');

// Stage 1 Approval (Manager)
$pipeline->approve($expense, approverId: 101, comment: 'Approved by Manager');
t($pipeline->getStatus($expense) === 'pending', 'Pipeline remains "pending" after Stage 1');

$req2 = $pipeline->getCurrentRequest($expense);
t($req2['stage_name'] === 'Finance Approval' && (int)$req2['approver_id'] === 102, 'Pipeline advanced to Stage 2 (Finance)');

// Stage 2 Approval (Finance)
$pipeline->approve($expense, approverId: 102, comment: 'Budget verified');

$req3 = $pipeline->getCurrentRequest($expense);
t($req3['stage_name'] === 'Executive Approval' && (int)$req3['approver_id'] === 103, 'Pipeline advanced to Stage 3 (Executive)');

// Stage 3 Final Approval (Executive)
$pipeline->approve($expense, approverId: 103, comment: 'Final executive approval');
t($pipeline->getStatus($expense) === 'approved', 'Approval status becomes "approved" after all stages completed');

// Test Rejection flow on second expense
$expense2 = (object)['id' => 502, 'title' => 'Unauthorized Software'];
$pipeline->submit($expense2);
$pipeline->reject($expense2, approverId: 101, comment: 'Rejected: Not in policy');
t($pipeline->getStatus($expense2) === 'rejected', 'Approval status becomes "rejected" upon stage rejection');

// Query pending requests for specific user
$pendingForUser = ApprovalPipeline::getPendingRequestsForUser(101);
t(is_array($pendingForUser), 'ApprovalPipeline::getPendingRequestsForUser() returns array');

// =============================================================================
// Section 4: HasWorkflow Model Trait
// =============================================================================
echo "\n\033[36m[Section 4: HasWorkflow ActiveRecord Model Trait]\033[0m\n";

$conn->statement("
    CREATE TABLE IF NOT EXISTS test_invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        created_at TEXT NULL,
        updated_at TEXT NULL
    )
");

class TestInvoice extends Model
{
    use HasWorkflow;

    protected string $table = 'test_invoices';
    protected array $fillable = ['id', 'title', 'status'];
    protected string $workflowName = 'invoice';
    protected string $approvalPipelineName = 'expense_claim';
    protected bool $timestamps = false;
}

$conn->insert("INSERT INTO test_invoices (title, status) VALUES (?, ?)", ['Server Hosting INV-01', 'draft']);
$invId = (int)$conn->getPdo()->lastInsertId();

$mgr = $app->make('workflow');
$mgr->define('invoice', $sm);
$mgr->definePipeline('expense_claim', $pipeline);

$invModel = new TestInvoice(['id' => $invId, 'title' => 'Server Hosting INV-01', 'status' => 'draft']);
$invModel->exists = true;

t($invModel->canTransition('issue') === true, 'HasWorkflow: canTransition("issue") allowed on model');
t($invModel->canTransition('pay') === false, 'HasWorkflow: canTransition("pay") blocked on draft model');

// Apply transition through model
$invModel->applyTransition('issue', ['actor_id' => 1, 'comment' => 'Model issue']);
t($invModel->getAttribute('status') === 'issued', 'HasWorkflow: applyTransition("issue") mutates model status');

$modelTransitions = $invModel->getAvailableTransitions();
t(isset($modelTransitions['pay']), 'HasWorkflow: getAvailableTransitions() reflects new state transitions');

// Approval workflow through model
$invModel->submitForApproval('expense_claim');
t($invModel->getApprovalStatus('expense_claim') === 'pending', 'HasWorkflow: submitForApproval() submits model to pipeline');

$invModel->approve(101, 'Approved stage 1', 'expense_claim');
t($invModel->getApprovalStatus('expense_claim') === 'pending', 'HasWorkflow: approve() advances model approval pipeline');

// =============================================================================
// Section 5: WorkflowManager, Global Helpers & Container
// =============================================================================
echo "\n\033[36m[Section 5: WorkflowManager, Global Helpers & Container]\033[0m\n";

t(function_exists('workflow'), 'Global helper workflow() exists');
t(workflow() instanceof WorkflowManager, 'workflow() returns WorkflowManager');

$wfOrder = workflow()->define('order_flow', function (StateMachine $m) {
    $m->addState(State::make('pending')->initial())
      ->addState(State::make('shipped'))
      ->addTransition(Transition::make('ship', 'pending', 'shipped'));
});

t($wfOrder instanceof StateMachine, 'WorkflowManager::define() creates and registers StateMachine');
t(workflow('order_flow') instanceof StateMachine, 'workflow("order_flow") resolves StateMachine');
t(workflow()->has('order_flow') === true, 'WorkflowManager::has("order_flow") returns true');

$cWorkflow = $app->make('workflow');
t($cWorkflow instanceof WorkflowManager, '"workflow" singleton resolved from container');

// Clean up test sqlite
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}

echo "\n=================================================================\n";
echo "  Phase 37 Results: {$passed}/{$total} Passed";
if ($failed > 0) {
    echo " | \033[31m{$failed} Failed\033[0m";
} else {
    echo " \033[32m(100%)\033[0m";
}
echo "\n=================================================================\n\n";

exit($failed > 0 ? 1 : 0);