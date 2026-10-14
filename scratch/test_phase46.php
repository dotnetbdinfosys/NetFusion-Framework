<?php
declare(strict_types=1);

/**
 * NetFusion Framework - Phase 46 Test Suite
 * Enterprise PBAC, ABAC & ReBAC (Google Zanzibar) Authorization Engine
 *
 * @author DotNetBD <support@dotnetbd.com>
 * @license MIT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Auth\Policy\Effect;
use NetFusion\Core\Auth\Policy\EvaluationContext;
use NetFusion\Core\Auth\Policy\Decision;
use NetFusion\Core\Auth\Policy\Rule;
use NetFusion\Core\Auth\Policy\Policy;
use NetFusion\Core\Auth\Policy\Evaluator\ConditionEvaluator;
use NetFusion\Core\Auth\Policy\PolicyEngine;
use NetFusion\Core\Auth\Policy\Store\ArrayPolicyStore;
use NetFusion\Core\Auth\Policy\Store\DatabasePolicyStore;
use NetFusion\Core\Auth\Policy\Rebac\RebacTuple;
use NetFusion\Core\Auth\Policy\Rebac\RebacGraphResolver;
use NetFusion\Core\Auth\Policy\Rebac\RebacManager;
use NetFusion\Core\Auth\Policy\Store\ArrayRebacStore;
use NetFusion\Core\Auth\Policy\Store\DatabaseRebacStore;
use NetFusion\Core\Auth\Policy\AccessManager;
use NetFusion\Core\Auth\Policy\Traits\HasPolicyAuthorization;
use NetFusion\Core\Auth\Policy\Middleware\AuthorizePolicyMiddleware;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

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
echo "   NetFusion Phase 46: PBAC, ABAC & ReBAC Test Suite   \n";
echo "=======================================================\n\n";

// -----------------------------------------------------------------------------
// 1. ABAC Condition Evaluator Tests
// -----------------------------------------------------------------------------
echo "\n--- 1. ABAC Condition Evaluator ---\n";

$evaluator = new ConditionEvaluator();
$ctx = new EvaluationContext(
    subject: ['id' => 101, 'role' => 'editor', 'department' => 'engineering', 'level' => 4],
    action: 'documents:update',
    resource: ['id' => 500, 'type' => 'document', 'owner_id' => 101, 'department' => 'engineering', 'confidential' => false, 'tags' => ['tech', 'architecture']],
    environment: ['ip' => '192.168.1.50', 'time_hour' => 14, 'maintenance' => false]
);

// Equality and comparison
assertTest('ABAC: eq condition passes', $evaluator->evaluate(['subject.role' => 'editor'], $ctx));
assertTest('ABAC: eq condition fails on mismatch', !$evaluator->evaluate(['subject.role' => 'admin'], $ctx));
assertTest('ABAC: gte numeric comparison', $evaluator->evaluate(['subject.level' => ['gte' => 3]], $ctx));
assertTest('ABAC: in operator on scalar', $evaluator->evaluate(['subject.role' => ['in' => ['editor', 'admin']]], $ctx));
assertTest('ABAC: contains operator on array attribute', $evaluator->evaluate(['resource.tags' => ['contains' => 'architecture']], $ctx));
assertTest('ABAC: starts_with operator', $evaluator->evaluate(['environment.ip' => ['starts_with' => '192.168.']], $ctx));
assertTest('ABAC: between operator', $evaluator->evaluate(['environment.time_hour' => ['between' => [9, 17]]], $ctx));

// Attribute Pointer comparison (Dynamic ABAC)
assertTest(
    'ABAC: Attribute pointer equality (subject.department == resource.department)',
    $evaluator->evaluate(['subject.department' => ['eq' => '$resource.department']], $ctx)
);
assertTest(
    'ABAC: Attribute pointer equality (subject.id == resource.owner_id)',
    $evaluator->evaluate(['subject.id' => ['eq' => '$resource.owner_id']], $ctx)
);

// Logical Combinators: $and, $or, $not
assertTest(
    'ABAC: Logical $and condition',
    $evaluator->evaluate([
        '$and' => [
            ['subject.role' => 'editor'],
            ['resource.confidential' => false],
        ]
    ], $ctx)
);

assertTest(
    'ABAC: Logical $or condition',
    $evaluator->evaluate([
        '$or' => [
            ['subject.role' => 'superadmin'],
            ['subject.id' => ['eq' => '$resource.owner_id']],
        ]
    ], $ctx)
);

assertTest(
    'ABAC: Logical $not condition',
    $evaluator->evaluate([
        '$not' => ['environment.maintenance' => true]
    ], $ctx)
);

// -----------------------------------------------------------------------------
// 2. PBAC Policy & Rules Evaluation Tests
// -----------------------------------------------------------------------------
echo "\n--- 2. PBAC Policy & PolicyEngine ---\n";

$policyStore = new ArrayPolicyStore();
$policyEngine = new PolicyEngine($policyStore);

$docPolicy = Policy::make('doc_access', 'Document Access Policy', 100)
    ->allow('documents:read', 'documents:*', ['description' => 'Anyone can read public documents'])
    ->allow('documents:update', 'documents:*', [
        'description' => 'Owner or same department can update',
        'conditions' => [
            '$or' => [
                ['subject.id' => ['eq' => '$resource.owner_id']],
                ['subject.department' => ['eq' => '$resource.department']]
            ]
        ]
    ])
    ->deny('documents:update', 'documents:*', [
        'description' => 'Strictly deny update if resource is locked',
        'conditions' => ['resource.locked' => true],
        'priority' => 10
    ]);

$policyEngine->register($docPolicy);

// Case A: Read should be allowed
$readDecision = $policyEngine->evaluate(new EvaluationContext(
    subject: ['id' => 202],
    action: 'documents:read',
    resource: ['type' => 'documents', 'id' => 500, 'locked' => false]
));
assertTest('PBAC: Allow read matches wildcard', $readDecision->isAllowed());

// Case B: Update by owner when not locked -> Allowed
$updateDecision = $policyEngine->evaluate(new EvaluationContext(
    subject: ['id' => 101, 'department' => 'eng'],
    action: 'documents:update',
    resource: ['type' => 'documents', 'id' => 500, 'owner_id' => 101, 'department' => 'eng', 'locked' => false]
));
assertTest('PBAC: Owner update permitted', $updateDecision->isAllowed());

// Case C: Update when locked -> Deny First override wins
$lockedDecision = $policyEngine->evaluate(new EvaluationContext(
    subject: ['id' => 101, 'department' => 'eng'],
    action: 'documents:update',
    resource: ['type' => 'documents', 'id' => 500, 'owner_id' => 101, 'department' => 'eng', 'locked' => true]
));
assertTest('PBAC: Deny-first overrides allow when locked is true', $lockedDecision->isDenied());
assertTest('PBAC: Decision contains matched denying rule ID', $lockedDecision->getRuleId() !== null);

// -----------------------------------------------------------------------------
// 3. ReBAC (Google Zanzibar Style) Tests
// -----------------------------------------------------------------------------
echo "\n--- 3. ReBAC (Google Zanzibar Style) ---\n";

$rebacStore = new ArrayRebacStore();
$graphResolver = new RebacGraphResolver($rebacStore);
$rebacManager = new RebacManager($rebacStore, $graphResolver);

// Define Schema Relations (e.g. folder contains documents, folder editor is doc editor)
$rebacManager->schema([
    'folder' => [
        'editor' => ['owner'],
        'viewer' => ['editor', 'viewer'],
    ],
    'document' => [
        'editor' => ['owner', 'parent.editor'],
        'viewer' => ['editor', 'parent.viewer'],
    ]
]);

// Write direct relation tuple: user:alice is owner of folder:f1
$tuple1 = RebacTuple::fromString('folder:f1#owner@user:alice');
$rebacManager->write($tuple1);

// Write userset tuple: group:devs#member is viewer of document:d1
$tuple2 = RebacTuple::fromString('document:d1#viewer@group:devs#member');
$rebacManager->write($tuple2);

// Write membership tuple: user:bob is member of group:devs
$tuple3 = RebacTuple::fromString('group:devs#member@user:bob');
$rebacManager->write($tuple3);

// Write parent tuple: document:d2 has parent folder:f1
$tuple4 = RebacTuple::fromString('document:d2#parent@folder:f1');
$rebacManager->write($tuple4);

// Test Direct check
assertTest('ReBAC: Alice is owner of folder:f1', $rebacManager->check('folder:f1', 'owner', 'user:alice'));
assertTest('ReBAC: Charlie is not owner of folder:f1', !$rebacManager->check('folder:f1', 'owner', 'user:charlie'));

// Test Group / Userset expansion: Bob is member of devs -> Bob is viewer of d1
assertTest('ReBAC: Bob is viewer of document:d1 via group:devs membership', $rebacManager->check('document:d1', 'viewer', 'user:bob'));
assertTest('ReBAC: Charlie is not viewer of document:d1', !$rebacManager->check('document:d1', 'viewer', 'user:charlie'));

// Test Hierarchy & Tuple-to-Userset Schema expansion:
// Alice is owner of folder:f1 -> Alice is editor of folder:f1
// folder:f1 is parent of document:d2 -> Alice is editor of document:d2 -> Alice is viewer of document:d2
assertTest('ReBAC: Alice is editor of document:d2 via parent folder:f1', $rebacManager->check('document:d2', 'editor', 'user:alice'));
assertTest('ReBAC: Alice is viewer of document:d2 via schema inheritance (editor -> viewer)', $rebacManager->check('document:d2', 'viewer', 'user:alice'));

// Test Cycle Detection: Add circular reference (group:a#member@group:b#member, group:b#member@group:a#member)
$rebacManager->write(RebacTuple::fromString('group:alpha#member@group:beta#member'));
$rebacManager->write(RebacTuple::fromString('group:beta#member@group:alpha#member'));
$rebacManager->write(RebacTuple::fromString('doc:secret#viewer@group:alpha#member'));
// Checking a non-member should safely terminate without stack overflow
assertTest('ReBAC: Cycle detection safely handles mutual circular group references', !$rebacManager->check('doc:secret', 'viewer', 'user:outsider'));

// Test Delete Tuple
$rebacManager->delete('group:devs#member@user:bob');
assertTest('ReBAC: Deleted relation revokes permission', !$rebacManager->check('document:d1', 'viewer', 'user:bob'));

// -----------------------------------------------------------------------------
// 4. Database Persistence Tests (SQLite In-Memory)
// -----------------------------------------------------------------------------
echo "\n--- 4. Database Persistence (SQLite) ---\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create PBAC and ReBAC tables
$migration = require __DIR__ . '/../database/migrations/2026_09_20_000026_create_pbac_and_rebac_tables.php';
$migration->up($pdo);

$dbPolicyStore = new DatabasePolicyStore($pdo);
$dbPolicy = Policy::make('db_policy', 'Persisted Policy', 50)
    ->allow('orders:create', 'orders:*')
    ->deny('orders:delete', 'orders:*', ['conditions' => ['resource.settled' => true]]);

$dbPolicyStore->save($dbPolicy);
assertTest('DB PolicyStore: Policy persisted successfully', $dbPolicyStore->has('db_policy'));

$retrievedPolicy = $dbPolicyStore->get('db_policy');
assertTest('DB PolicyStore: Retrieved policy has correct name and rules count', $retrievedPolicy !== null && count($retrievedPolicy->getRules()) === 2);

$dbRebacStore = new DatabaseRebacStore($pdo);
$tupleDb = RebacTuple::fromString('company:acme#admin@user:super');
$dbRebacStore->write($tupleDb);

assertTest('DB RebacStore: Tuple exists in database', $dbRebacStore->exists($tupleDb));

$tuples = $dbRebacStore->find(['entity_type' => 'company', 'entity_id' => 'acme']);
assertTest('DB RebacStore: Find returns written tuple', count($tuples) === 1 && $tuples[0]->getSubjectId() === 'super');

$dbRebacStore->delete($tupleDb);
assertTest('DB RebacStore: Tuple deleted successfully', !$dbRebacStore->exists($tupleDb));

// -----------------------------------------------------------------------------
// 5. AccessManager, Trait & Middleware Tests
// -----------------------------------------------------------------------------
echo "\n--- 5. AccessManager, Trait & Middleware ---\n";

$app = new Application(dirname(__DIR__));
$accessManager = new AccessManager($app, $policyEngine, $rebacManager);
$app->instance(AccessManager::class, $accessManager);
$app->instance('access.manager', $accessManager);

// Define policy on AccessManager
$accessManager->define('finance_policy', function (Policy $p) {
    $p->allow('invoices:approve', 'invoices:*', [
        'conditions' => [
            'subject.limit' => ['gte' => '$resource.amount']
        ]
    ]);
});

$managerSubject = ['id' => 10, 'role' => 'manager', 'limit' => 5000];
$smallInvoice = ['type' => 'invoices', 'id' => 1, 'amount' => 2500];
$largeInvoice = ['type' => 'invoices', 'id' => 2, 'amount' => 10000];

assertTest('AccessManager: can() returns true when limit >= amount', $accessManager->can($managerSubject, 'invoices:approve', $smallInvoice));
assertTest('AccessManager: cannot() returns true when limit < amount', $accessManager->cannot($managerSubject, 'invoices:approve', $largeInvoice));

// Model Trait Test
class TestUser {
    use HasPolicyAuthorization;

    public int $id = 77;
    public string $role = 'manager';
    public int $limit = 5000;

    public function getPolicySubject(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'limit' => $this->limit
        ];
    }
}

$testUser = new TestUser();
assertTest('HasPolicyAuthorization Trait: User->can() works directly', $testUser->can('invoices:approve', $smallInvoice));
assertTest('HasPolicyAuthorization Trait: User->cannot() works directly', $testUser->cannot('invoices:approve', $largeInvoice));

// Middleware Test
$middleware = new AuthorizePolicyMiddleware();
$request = new Request('POST', '/api/invoices/1/approve');
$request->setAttribute('user', $managerSubject);
$request->setAttribute('invoice', $smallInvoice);

$response = $middleware->handle($request, function ($req) {
    return new Response('Approved successfully', 200);
}, 'invoices:approve', 'invoice');

assertTest('AuthorizePolicyMiddleware: Grants access when authorized', $response->getStatusCode() === 200);

$unauthorizedRequest = new Request('POST', '/api/invoices/2/approve');
$unauthorizedRequest->setAttribute('user', $managerSubject);
$unauthorizedRequest->setAttribute('invoice', $largeInvoice);

$forbiddenResponse = $middleware->handle($unauthorizedRequest, function ($req) {
    return new Response('Approved', 200);
}, 'invoices:approve', 'invoice');

assertTest('AuthorizePolicyMiddleware: Returns 403 when authorization fails', $forbiddenResponse->getStatusCode() === 403);

// -----------------------------------------------------------------------------
// 6. Global Helpers & DI Tests
// -----------------------------------------------------------------------------
echo "\n--- 6. Global Helpers & DI Resolution ---\n";

assertTest('Global Helper: can_access() is functional', can_access($managerSubject, 'invoices:approve', $smallInvoice));
assertTest('Global Helper: policy() returns PolicyEngineInterface', policy() instanceof \NetFusion\Core\Auth\Policy\Contracts\PolicyEngineInterface);
assertTest('Global Helper: rebac() returns RebacManager', rebac() instanceof RebacManager);

echo "\n=======================================================\n";
echo "   Phase 46 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
