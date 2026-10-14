<?php

/**
 * DotNetBD Enterprise Framework
 *
 * Phase 59 Test Suite: Enterprise Dynamic Business Rule Engine, RETE Algorithm & DMN 1.3 Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use NetFusion\Core\Application;
use NetFusion\Core\RuleEngine\Contracts\FactInterface;
use NetFusion\Core\RuleEngine\Contracts\WorkingMemoryInterface;
use NetFusion\Core\RuleEngine\DMN\DecisionResult;
use NetFusion\Core\RuleEngine\DMN\DecisionRule;
use NetFusion\Core\RuleEngine\DMN\DecisionTable;
use NetFusion\Core\RuleEngine\DMN\HitPolicy;
use NetFusion\Core\RuleEngine\DSL\DecisionTableBuilder;
use NetFusion\Core\RuleEngine\DSL\RuleBuilder;
use NetFusion\Core\RuleEngine\Exceptions\InfiniteLoopException;
use NetFusion\Core\RuleEngine\Exceptions\RuleEvaluationException;
use NetFusion\Core\RuleEngine\Fact\Fact;
use NetFusion\Core\RuleEngine\Fact\FactCollection;
use NetFusion\Core\RuleEngine\FEEL\FeelEvaluator;
use NetFusion\Core\RuleEngine\Rete\Activation;
use NetFusion\Core\RuleEngine\Rete\Agenda;
use NetFusion\Core\RuleEngine\Rete\AlphaNode;
use NetFusion\Core\RuleEngine\Rete\BetaNode;
use NetFusion\Core\RuleEngine\Rete\ReteNetwork;
use NetFusion\Core\RuleEngine\Rete\TypeNode;
use NetFusion\Core\RuleEngine\Rete\WorkingMemory;
use NetFusion\Core\RuleEngine\RuleEngineManager;
use NetFusion\Core\RuleEngine\Store\ArrayRuleStore;
use NetFusion\Core\RuleEngine\Store\DatabaseRuleStore;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "================================================================================\n";
echo "       PHASE 59: DYNAMIC BUSINESS RULE ENGINE, RETE & DMN 1.3 TEST SUITE        \n";
echo "================================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Facts & Working Memory
// -----------------------------------------------------------------------------
echo "[1] Testing Facts & Working Memory...\n";

$factOrder = new Fact('Order', [
    'id' => 'ORD-1001',
    'customerId' => 'CUST-50',
    'total' => 750.0,
    'shipping' => [
        'country' => 'BD',
        'city' => 'Dhaka',
    ],
]);

assertTest($factOrder->getType() === 'Order', "Fact reports correct type");
assertTest($factOrder->get('total') === 750.0, "Fact gets top-level attribute");
assertTest($factOrder->get('shipping.city') === 'Dhaka', "Fact resolves nested dot-notation attribute");
assertTest($factOrder->has('shipping.country'), "Fact has() checks attribute existence");
assertTest(!empty($factOrder->getHash()), "Fact generates state hash");

$modifiedOrder = $factOrder->with(['total' => 800.0]);
assertTest($modifiedOrder->get('total') === 800.0, "Fact with() creates updated immutable clone");

$wm = new WorkingMemory();
$asserted = $wm->assertFact($factOrder);
assertTest($wm->count() === 1, "WorkingMemory asserts fact");
assertTest($wm->getFactById($factOrder->getId()) !== null, "WorkingMemory retrieves fact by ID");
assertTest(count($wm->getFacts('Order')) === 1, "WorkingMemory retrieves facts by type");

$wm->modifyFact($factOrder->getId(), ['total' => 950.0]);
assertTest($wm->getFactById($factOrder->getId())->get('total') === 950.0, "WorkingMemory modifies existing fact attributes");

$wm->retractFact($factOrder->getId());
assertTest($wm->count() === 0, "WorkingMemory retracts fact");

// -----------------------------------------------------------------------------
// 2. RETE Network & Conflict Resolution
// -----------------------------------------------------------------------------
echo "\n[2] Testing RETE Pattern Matching Network & Agenda...\n";

$network = new ReteNetwork();
$wmNet = $network->getWorkingMemory();

// Define Rule 1: High value order
$ruleHighValue = RuleBuilder::name('high_value_order')
    ->salience(10)
    ->when('Order', fn(FactInterface $f) => (float)$f->get('total', 0) >= 500.0)
    ->then(function (FactInterface $order, WorkingMemoryInterface $wm) {
        $wm->assertFact(new Fact('Tag', ['orderId' => $order->get('id'), 'tag' => 'HIGH_VALUE']));
    });

// Define Rule 2: Multi-pattern join (Order + VIP Customer -> Discount)
$ruleVipDiscount = RuleBuilder::name('vip_order_discount')
    ->salience(20) // Higher salience than high_value_order
    ->when('Order', fn(FactInterface $o) => (float)$o->get('total', 0) >= 300.0)
    ->and('Customer', fn(FactInterface $c, FactInterface $o) => $c->get('id') === $o->get('customerId') && $c->get('tier') === 'VIP')
    ->then(function (FactInterface $order, FactInterface $customer, WorkingMemoryInterface $wm) {
        $wm->assertFact(new Fact('Discount', [
            'orderId' => $order->get('id'),
            'percent' => 15,
            'reason' => 'VIP Club Promo',
        ]));
    });

$network->addRule($ruleHighValue);
$network->addRule($ruleVipDiscount);

// Assert facts
$factO1 = new Fact('Order', ['id' => 'ORD-1', 'customerId' => 'CUST-1', 'total' => 600.0]);
$factC1 = new Fact('Customer', ['id' => 'CUST-1', 'tier' => 'VIP', 'name' => 'John Doe']);

$wmNet->assertFact($factO1);
$wmNet->assertFact($factC1);

$agenda = $network->getAgenda();
assertTest($agenda->count() >= 2, "RETE network matched multi-pattern conditions into Agenda");

// Fire all rules
$firings = $network->fireAllRules();
assertTest($firings >= 2, "RETE network executed rule activations");

$discounts = $wmNet->getFacts('Discount');
assertTest(count($discounts) === 1 && $discounts[0]->get('percent') === 15, "Multi-pattern join rule asserted Discount fact");

$tags = $wmNet->getFacts('Tag');
assertTest(count($tags) === 1 && $tags[0]->get('tag') === 'HIGH_VALUE', "Single-pattern rule asserted Tag fact");

// Test Refraction: Running fireAllRules again should fire 0 rules because facts haven't changed
$secondFirings = $network->fireAllRules();
assertTest($secondFirings === 0, "Refraction prevents duplicate rule execution without state change");

// Test Infinite Loop Detection
$loopNetwork = new ReteNetwork();
$loopNetwork->addRule(
    RuleBuilder::name('infinite_loop_trigger')
        ->when('Ping', fn() => true)
        ->then(function (FactInterface $p, WorkingMemoryInterface $wm) {
            $wm->assertFact(new Fact('Ping', ['seq' => rand(1, 999999)]));
        })
);
$loopNetwork->getWorkingMemory()->assertFact(new Fact('Ping', ['seq' => 1]));

$loopDetected = false;
try {
    $loopNetwork->fireAllRules(10); // Limit to 10 firings
} catch (InfiniteLoopException $e) {
    $loopDetected = true;
}
assertTest($loopDetected, "RETE network detects infinite loop and throws InfiniteLoopException");

// -----------------------------------------------------------------------------
// 3. FEEL Expression Language Evaluator
// -----------------------------------------------------------------------------
echo "\n[3] Testing FEEL (Friendly Enough Expression Language) Evaluator...\n";

$feel = new FeelEvaluator();

// Unary Tests
assertTest($feel->evaluateUnaryTest('-', 100), "FEEL dash '-' unary test matches anything");
assertTest($feel->evaluateUnaryTest('>= 100', 150), "FEEL comparison '>= 100' matches 150");
assertTest(!$feel->evaluateUnaryTest('>= 100', 50), "FEEL comparison '>= 100' rejects 50");
assertTest($feel->evaluateUnaryTest('[18..65]', 25), "FEEL closed range '[18..65]' matches 25");
assertTest($feel->evaluateUnaryTest('[18..65]', 18), "FEEL closed range '[18..65]' matches lower bound 18");
assertTest(!$feel->evaluateUnaryTest('(18..65]', 18), "FEEL open range '(18..65]' rejects lower bound 18");
assertTest($feel->evaluateUnaryTest('"GOLD", "PLATINUM"', 'PLATINUM'), "FEEL comma-separated string list matches 'PLATINUM'");
assertTest(!$feel->evaluateUnaryTest('"GOLD", "PLATINUM"', 'SILVER'), "FEEL string list rejects 'SILVER'");
assertTest($feel->evaluateUnaryTest('not("BRONZE")', 'GOLD'), "FEEL negation not('BRONZE') matches 'GOLD'");

// General Expressions
assertTest($feel->evaluate('10 + 20 * 2') === 50.0, "FEEL arithmetic calculation");
assertTest($feel->evaluate('starts with("INV-2026-01", "INV-")') === true, "FEEL starts with() function");
assertTest($feel->evaluate('contains("Enterprise Edition", "prise")') === true, "FEEL contains() function");
assertTest($feel->evaluate('upper case("netfusion")') === 'NETFUSION', "FEEL upper case() function");
assertTest($feel->evaluate('if score >= 80 then "PASS" else "FAIL"', ['score' => 90]) === 'PASS', "FEEL if-then-else ternary conditional");
assertTest($feel->evaluate('order.subtotal * 0.10', ['order' => ['subtotal' => 500]]) === 50.0, "FEEL variable access with arithmetic");
assertTest($feel->evaluate('list contains(["admin", "manager", "user"], role)', ['role' => 'admin']) === true, "FEEL list contains() function");

// -----------------------------------------------------------------------------
// 4. DMN 1.3 Decision Table Engine & Hit Policies
// -----------------------------------------------------------------------------
echo "\n[4] Testing DMN 1.3 Decision Tables & Hit Policies...\n";

// 1. FIRST Hit Policy (Loan Eligibility Decision)
$loanTable = DecisionTableBuilder::name('loan_eligibility')
    ->hitPolicy(HitPolicy::FIRST)
    ->input('creditScore', 'Credit Score', 'integer')
    ->input('annualIncome', 'Annual Income', 'number')
    ->output('status', 'Approval Status', 'string', 'REJECTED')
    ->output('interestRate', 'Interest Rate', 'number', 0.0)
    ->rule(['>= 750', '>= 60000'], ['APPROVED', 4.5], 'Tier 1 Prime')
    ->rule(['[650..749]', '>= 40000'], ['APPROVED', 6.5], 'Tier 2 Standard')
    ->rule(['[600..649]', '>= 30000'], ['REVIEW', 9.0], 'Tier 3 Manual Review')
    ->rule(['-', '-'], ['REJECTED', 0.0], 'Default Decline')
    ->build();

$resPrime = $loanTable->evaluate(['creditScore' => 780, 'annualIncome' => 85000]);
assertTest($resPrime->get('status') === 'APPROVED' && $resPrime->get('interestRate') === 4.5, "DMN FIRST policy selects Tier 1 Prime rule");

$resReview = $loanTable->evaluate(['creditScore' => 620, 'annualIncome' => 35000]);
assertTest($resReview->get('status') === 'REVIEW', "DMN FIRST policy selects Tier 3 Manual Review");

$resDecline = $loanTable->evaluate(['creditScore' => 500, 'annualIncome' => 20000]);
assertTest($resDecline->get('status') === 'REJECTED', "DMN FIRST policy falls back to default decline");

// 2. UNIQUE Hit Policy
$uniqueTable = DecisionTableBuilder::name('tax_bracket')
    ->hitPolicy(HitPolicy::UNIQUE)
    ->input('income')
    ->output('rate', 'Tax Rate', 'number', 0.0)
    ->rule(['< 10000'], [0.0])
    ->rule(['[10000..50000]'], [10.0])
    ->rule(['> 50000'], [20.0])
    ->build();

$taxRes = $uniqueTable->evaluate(['income' => 25000]);
assertTest($taxRes->getOutputs() === 10.0, "DMN UNIQUE policy evaluates single matching bracket");

// 3. COLLECT_SUM Hit Policy (Routing Points / Penalties)
$riskPointsTable = DecisionTableBuilder::name('fraud_risk_score')
    ->hitPolicy(HitPolicy::COLLECT_SUM)
    ->input('amount')
    ->input('isForeign')
    ->input('failedLogins')
    ->output('riskPoints', 'Risk Points', 'integer', 0)
    ->rule(['> 1000', '-', '-'], [30], 'Large transaction')
    ->rule(['-', 'true', '-'], [25], 'Foreign transaction')
    ->rule(['-', '-', '>= 3'], [40], 'Multiple failed logins')
    ->build();

$riskRes = $riskPointsTable->evaluate(['amount' => 1500, 'isForeign' => true, 'failedLogins' => 4]);
assertTest($riskRes->getOutputs() === 95.0, "DMN COLLECT_SUM policy aggregates 30 + 25 + 40 = 95 risk points");

// 4. RULE_ORDER Hit Policy (Recommendation list)
$recTable = DecisionTableBuilder::name('user_perks')
    ->hitPolicy(HitPolicy::RULE_ORDER)
    ->input('spending')
    ->output('perk')
    ->rule(['>= 100'], ['Free Shipping'])
    ->rule(['>= 500'], ['10% Cash Back'])
    ->rule(['>= 1000'], ['VIP Concierge'])
    ->build();

$perksRes = $recTable->evaluate(['spending' => 600]);
$perks = $perksRes->getOutputs();
assertTest(count($perks) === 2 && $perks[0] === 'Free Shipping' && $perks[1] === '10% Cash Back', "DMN RULE_ORDER returns ordered list of all matched perks");

// -----------------------------------------------------------------------------
// 5. Stores (ArrayRuleStore & DatabaseRuleStore)
// -----------------------------------------------------------------------------
echo "\n[5] Testing Persistence Stores (Array & SQLite DatabaseRuleStore)...\n";

$arrayStore = new ArrayRuleStore();
$arrayStore->saveRule('rule_test', ['salience' => 5, 'description' => 'Test Rule']);
assertTest($arrayStore->getRule('rule_test')['salience'] === 5, "ArrayRuleStore saves and retrieves rule");

$auditId = $arrayStore->logAudit(['rule_name' => 'rule_test', 'result' => 'OK']);
assertTest(!empty($auditId) && count($arrayStore->getAuditLogs()) === 1, "ArrayRuleStore records and queries audit trail");

// Test SQLite DatabaseRuleStore
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_21_000039_create_rule_engine_tables.php';
$migration->up($pdo);

$dbStore = new DatabaseRuleStore($pdo);
$dbStore->saveRule('db_promo_rule', [
    'description' => 'Holiday discount',
    'salience' => 15,
    'patterns' => [['type' => 'Cart']],
    'enabled' => true,
]);

$savedRule = $dbStore->getRule('db_promo_rule');
assertTest($savedRule !== null && $savedRule['salience'] === 15, "DatabaseRuleStore persists and loads rule from SQLite");

$dbStore->saveDecisionTable('db_tax_table', $uniqueTable->jsonSerialize());
$savedTable = $dbStore->getDecisionTable('db_tax_table');
assertTest($savedTable !== null && $savedTable['hit_policy'] === 'UNIQUE', "DatabaseRuleStore persists and loads DMN table");

$dbAuditId = $dbStore->logAudit(['decision_table' => 'db_tax_table', 'result' => 10.0]);
assertTest(!empty($dbAuditId) && count($dbStore->getAuditLogs()) === 1, "DatabaseRuleStore logs audit trail in SQLite");

// -----------------------------------------------------------------------------
// 6. RuleEngineManager & Global Helpers
// -----------------------------------------------------------------------------
echo "\n[6] Testing RuleEngineManager & Global Helpers...\n";

$app = Application::getInstance();
$mgr = new RuleEngineManager($dbStore);
$mgr->addDecisionTable($loanTable);

$app->instance('rule_engine', $mgr);
$app->instance('rules', $mgr);

assertTest(function_exists('rule_engine'), "Global helper rule_engine() exists");
assertTest(function_exists('fact'), "Global helper fact() exists");
assertTest(function_exists('rule'), "Global helper rule() exists");
assertTest(function_exists('decision_table'), "Global helper decision_table() exists");
assertTest(function_exists('feel'), "Global helper feel() exists");

$helperFact = fact('Inventory', ['sku' => 'SKU-99', 'qty' => 5]);
assertTest($helperFact->get('sku') === 'SKU-99', "fact() helper creates Fact instance");

$helperFeelRes = feel('upper case("dotnetbd")');
assertTest($helperFeelRes === 'DOTNETBD', "feel() helper evaluates expression");

$decResult = $mgr->evaluateDecision('loan_eligibility', ['creditScore' => 800, 'annualIncome' => 100000]);
assertTest($decResult->get('status') === 'APPROVED', "RuleEngineManager::evaluateDecision() executes registered table");

$metrics = $mgr->getMetrics();
assertTest(isset($metrics['uptime_seconds']) && $metrics['decision_tables_count'] >= 1, "RuleEngineManager provides system metrics");

// -----------------------------------------------------------------------------
// 7. Console Commands Registration
// -----------------------------------------------------------------------------
echo "\n[7] Testing Console Commands Registration...\n";

$kernel = $app->make(\NetFusion\Core\Console\Kernel::class);

$reflection = new ReflectionClass($kernel);
$commandsProperty = $reflection->getProperty('commands');
$commandsProperty->setAccessible(true);
$registeredCommands = $commandsProperty->getValue($kernel);

assertTest(isset($registeredCommands['rule:list']), "Command 'rule:list' is registered in Console Kernel");
assertTest(isset($registeredCommands['rule:eval']), "Command 'rule:eval' is registered in Console Kernel");
assertTest(isset($registeredCommands['decision:tables']), "Command 'decision:tables' is registered in Console Kernel");
assertTest(isset($registeredCommands['decision:eval']), "Command 'decision:eval' is registered in Console Kernel");

// Test CLI command execution
ob_start();
$exitCode = $kernel->handle(['netfusion', 'decision:tables']);
$output = ob_get_clean();

assertTest($exitCode === 0, "Command 'decision:tables' executed successfully with exit code 0");
assertTest(str_contains($output, 'DMN 1.3 Decision Tables'), "Command 'decision:tables' printed header table");

echo "\n================================================================================\n";
echo "           PHASE 59 TEST SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "================================================================================\n\n";

exit($failed > 0 ? 1 : 0);
