<?php

/**
 * DotNetBD Enterprise Framework - Phase 57 Comprehensive Test Suite
 * Enterprise Real-Time Complex Event Processing (CEP) & Streaming Pipeline Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\WindowInterface;
use NetFusion\Core\CEP\Event;
use NetFusion\Core\CEP\Watermark;
use NetFusion\Core\CEP\Window\TimeWindow;
use NetFusion\Core\CEP\Window\TumblingTimeWindow;
use NetFusion\Core\CEP\Window\SlidingTimeWindow;
use NetFusion\Core\CEP\Window\SessionWindow;
use NetFusion\Core\CEP\Window\CountWindow;
use NetFusion\Core\CEP\Window\WindowAggregate;
use NetFusion\Core\CEP\Window\WindowState;
use NetFusion\Core\CEP\Pattern\Pattern;
use NetFusion\Core\CEP\Pattern\PatternCondition;
use NetFusion\Core\CEP\Pattern\NFA;
use NetFusion\Core\CEP\Pattern\MatchedPattern;
use NetFusion\Core\CEP\Pattern\PatternStream;
use NetFusion\Core\CEP\Stream\DataStream;
use NetFusion\Core\CEP\Stream\Source\ArrayStreamSource;
use NetFusion\Core\CEP\Stream\Source\CallableStreamSource;
use NetFusion\Core\CEP\Stream\Source\IMDGStreamSource;
use NetFusion\Core\CEP\Stream\Sink\ArrayStreamSink;
use NetFusion\Core\CEP\Stream\Sink\CallableStreamSink;
use NetFusion\Core\CEP\Stream\Sink\LogStreamSink;
use NetFusion\Core\CEP\Stream\Sink\WorkflowStreamSink;
use NetFusion\Core\CEP\CEPRule;
use NetFusion\Core\CEP\RuleBuilder;
use NetFusion\Core\CEP\Store\ArrayCEPStore;
use NetFusion\Core\CEP\Store\DatabaseCEPStore;
use NetFusion\Core\CEP\CEPManager;
use NetFusion\Core\IMDG\DistributedMap;
use NetFusion\Core\Workflow\Definition\WorkflowBuilder;
use NetFusion\Core\Workflow\WorkflowEngine;
use NetFusion\Core\Console\Kernel;

$pass = 0;
$fail = 0;

function assertTest(bool $condition, string $testName): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  [PASS] {$testName}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$testName}\n";
    }
}

echo "================================================================================\n";
echo "           PHASE 57: REAL-TIME CEP & STREAMING PIPELINE ENGINE TEST SUITE       \n";
echo "================================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Core Event & Watermark Tests
// -----------------------------------------------------------------------------
echo "[1] Testing Event & Watermark Models...\n";

$evt = new Event('order.placed', ['order_id' => 'ORD-101', 'amount' => 250.50, 'user' => ['id' => 42, 'tier' => 'gold']], 1700000000.0, 'evt-1');
assertTest($evt->getId() === 'evt-1', "Event ID getter matches");
assertTest($evt->getType() === 'order.placed', "Event Type getter matches");
assertTest($evt->getTimestamp() === 1700000000.0, "Event Timestamp getter matches");
assertTest($evt->get('order_id') === 'ORD-101', "Event direct property access");
assertTest($evt->get('user.id') === 42, "Event dot notation nested access");
assertTest($evt->get('user.tier') === 'gold', "Event dot notation second property");
assertTest($evt->has('user.id'), "Event has() returns true for nested key");
assertTest(!$evt->has('user.nonexistent'), "Event has() returns false for missing key");

$clonedEvt = $evt->withPayload(['currency' => 'USD'])->withHeader('X-Trace-Id', 'trace-999');
assertTest($clonedEvt->get('currency') === 'USD', "withPayload() merges payload");
assertTest($clonedEvt->getHeader('X-Trace-Id') === 'trace-999', "withHeader() sets header");

$watermark = Watermark::of(1700000100.0, 10.0, Watermark::STRATEGY_DROP);
assertTest($watermark->getTimestamp() === 1700000100.0, "Watermark timestamp matches");
assertTest($watermark->isLate(1700000080.0), "Watermark detects late event (80 < 100 - 10)");
assertTest(!$watermark->isLate(1700000095.0), "Watermark allows event within lateness (95 >= 90)");

// -----------------------------------------------------------------------------
// 2. Window Assigners (Tumbling, Sliding, Session, Count)
// -----------------------------------------------------------------------------
echo "\n[2] Testing Window Assigners...\n";

$tumbling = TumblingTimeWindow::seconds(10);
$tWin = $tumbling->assignWindows(new Event('test', [], 105.0), 105.0);
assertTest(count($tWin) === 1, "TumblingWindow assigns exactly 1 window");
assertTest($tWin[0]->getStart() === 100.0 && $tWin[0]->getEnd() === 110.0, "TumblingWindow boundary is [100, 110)");
assertTest($tWin[0]->contains(105.0), "TimeWindow contains(105.0) is true");
assertTest(!$tWin[0]->contains(110.0), "TimeWindow contains(110.0) is false (exclusive end)");
assertTest($tWin[0]->isExpired(110.0), "TimeWindow isExpired(110.0) is true");

$sliding = SlidingTimeWindow::of(10.0, 5.0);
$sWins = $sliding->assignWindows(new Event('test', [], 107.0), 107.0);
assertTest(count($sWins) === 2, "SlidingWindow (size=10, slide=5) assigns 2 overlapping windows");
assertTest($sWins[0]->getStart() === 100.0 && $sWins[0]->getEnd() === 110.0, "Sliding window 1 is [100, 110)");
assertTest($sWins[1]->getStart() === 105.0 && $sWins[1]->getEnd() === 115.0, "Sliding window 2 is [105, 115)");

$countWin = CountWindow::tumbling(5);
assertTest($countWin->getSize() === 5 && !$countWin->isSliding(), "CountWindow tumbling configuration");

// -----------------------------------------------------------------------------
// 3. Window Aggregates & WindowState Lifecycle
// -----------------------------------------------------------------------------
echo "\n[3] Testing WindowState & Aggregators...\n";

$aggSum = WindowAggregate::sum('temp');
$aggAvg = WindowAggregate::avg('temp');
$aggStd = WindowAggregate::stddev('temp');
$aggPct = WindowAggregate::percentile('temp', 50.0);

$winState = new WindowState(TumblingTimeWindow::seconds(10), $aggAvg);

$winState->addEvent('sensor_1', new Event('temp', ['temp' => 20.0], 101.0), 101.0);
$winState->addEvent('sensor_1', new Event('temp', ['temp' => 30.0], 105.0), 105.0);
$winState->addEvent('sensor_1', new Event('temp', ['temp' => 40.0], 108.0), 108.0);

assertTest($winState->getActiveWindowCount() === 1, "WindowState buffered 1 active window");

// Advance watermark to 110.0 -> Should trigger the [100, 110) window
$fired = $winState->advanceWatermark(Watermark::of(110.0));
assertTest(count($fired) === 1, "Watermark advancement fired 1 expired window");
assertTest($fired[0]['key'] === 'sensor_1', "Fired result key matches");
assertTest($fired[0]['count'] === 3, "Fired result count is 3");
assertTest($fired[0]['value'] === 30.0, "Fired average value is 30.0 ((20+30+40)/3)");
assertTest($winState->getActiveWindowCount() === 0, "WindowState purged expired window");

// CountWindow in WindowState
$countState = new WindowState(CountWindow::tumbling(3), WindowAggregate::sum('val'));
$r1 = $countState->addEvent('k1', new Event('num', ['val' => 10], 1.0), 1.0);
$r2 = $countState->addEvent('k1', new Event('num', ['val' => 20], 2.0), 2.0);
assertTest(empty($r1) && empty($r2), "CountWindow does not fire before count threshold");
$r3 = $countState->addEvent('k1', new Event('num', ['val' => 30], 3.0), 3.0);
assertTest(count($r3) === 1, "CountWindow fires upon reaching threshold 3");
assertTest($r3[0]['value'] === 60.0, "CountWindow sum result is 60.0 (10+20+30)");

// -----------------------------------------------------------------------------
// 4. CEP Pattern Matching Engine (NFA)
// -----------------------------------------------------------------------------
echo "\n[4] Testing CEP NFA Pattern Matching Engine...\n";

// Pattern: Login Failed -> followedByAny Login Success within 60s (Brute force / account takeover detection)
$pattern = Pattern::begin('failed')
    ->where(fn(EventInterface $e) => $e->getType() === 'login_failed')
    ->followedByAny('success')
    ->where(fn(EventInterface $e) => $e->getType() === 'login_success')
    ->within(60.0);

$nfa = NFA::compileFrom($pattern);

// Step 1: Failed login
$m1 = $nfa->process('user_10', new Event('login_failed', ['ip' => '1.2.3.4'], 1000.0));
assertTest(empty($m1), "Initial pattern state does not complete full match");
assertTest($nfa->getActivePathCount('user_10') === 1, "NFA holds 1 active computation path for user_10");

// Step 2: Unrelated noise event (relaxed contiguity should ignore)
$m2 = $nfa->process('user_10', new Event('page_view', ['url' => '/home'], 1010.0));
assertTest(empty($m2), "Noise event does not trigger match");
assertTest($nfa->getActivePathCount('user_10') === 1, "NFA still holds 1 active computation path after noise");

// Step 3: Success login
$m3 = $nfa->process('user_10', new Event('login_success', ['ip' => '1.2.3.4'], 1020.0));
assertTest(count($m3) === 1, "NFA successfully matches full sequence: failed -> page_view -> success");
/** @var MatchedPattern $match */
$match = $m3[0];
assertTest($match->getPatternName() === 'failed', "MatchedPattern name matches root state");
assertTest($match->getPartitionKey() === 'user_10', "MatchedPattern partition key matches");
assertTest($match->get('failed')->get('ip') === '1.2.3.4', "MatchedPattern retrieves 'failed' event");
assertTest($match->get('success')->getType() === 'login_success', "MatchedPattern retrieves 'success' event");
assertTest($match->getDuration() === 20.0, "MatchedPattern duration is 20.0 seconds (1020 - 1000)");

// Test Time Window Expiration
$nfa->reset();
$nfa->process('user_20', new Event('login_failed', [], 2000.0));
// Event arrives 70s later (window is 60s)
$mExpired = $nfa->process('user_20', new Event('login_success', [], 2070.0));
assertTest(empty($mExpired), "NFA discards expired computation paths exceeding within() limit");

// -----------------------------------------------------------------------------
// 5. DataStream, KeyedStream, Sinks & Connectors
// -----------------------------------------------------------------------------
echo "\n[5] Testing DataStream Operators & Sinks...\n";

$stream = new DataStream('orders');
$sink = new ArrayStreamSink('orders_sink');

$stream->filter(fn(EventInterface $e) => (float)$e->get('amount', 0) > 100.0)
       ->map(fn(EventInterface $e) => $e->withPayload(['is_high_value' => true]))
       ->sink($sink);

$stream->emit(new Event('orders', ['amount' => 50.0]));
$stream->emit(new Event('orders', ['amount' => 150.0]));
$stream->emit(new Event('orders', ['amount' => 300.0]));

assertTest($sink->count() === 2, "DataStream filter passed 2 events > 100.0");
$items = $sink->getItems();
assertTest($items[0]->get('is_high_value') === true, "DataStream map added 'is_high_value' property");

// Test Sources (ArrayStreamSource, CallableStreamSource)
$arraySource = new ArrayStreamSource('test_src', [
    new Event('click', ['x' => 10]),
    new Event('click', ['x' => 20]),
]);
assertTest($arraySource->hasNext(), "ArrayStreamSource hasNext is true");
$polled = $arraySource->poll();
assertTest(count($polled) === 2, "ArrayStreamSource polled 2 items");
assertTest(!$arraySource->hasNext(), "ArrayStreamSource hasNext is false after polling");

// -----------------------------------------------------------------------------
// 6. IMDG Continuous Query Bridge & BPMN Workflow Trigger Sink
// -----------------------------------------------------------------------------
echo "\n[6] Testing IMDG Stream Bridge & Workflow Trigger Sink...\n";

// Test IMDGStreamSource
$imdgSource = new IMDGStreamSource('imdg_trades');
$imdgEvent = new \NetFusion\Core\IMDG\ContinuousQuery\EntryEvent('trades', 'trade_99', ['symbol' => 'AAPL', 'price' => 180.0], null, 'ADDED', 1700000000.0);
$imdgSource->onEntryEvent($imdgEvent);

$polledIMDG = $imdgSource->poll();
assertTest(count($polledIMDG) === 1, "IMDGStreamSource received IMDG continuous query event");
assertTest($polledIMDG[0]->getType() === 'imdg.added', "IMDGStreamSource converted to 'imdg.added' event");
assertTest($polledIMDG[0]->get('key') === 'trade_99', "IMDG event payload key matches");

// Test WorkflowStreamSink with Phase 55 Workflow Engine
$wfBuilder = WorkflowBuilder::create('fraud_investigation_process', 'Fraud Investigation Process');
$wfDef = $wfBuilder->start('start')
    ->connect('start', 'end')
    ->end('end')
    ->build();

$wfEngine = new WorkflowEngine();
$wfEngine->register($wfDef);

$wfSink = new WorkflowStreamSink('wf_sink', 'fraud_investigation_process', $wfEngine);
$matchedMock = new MatchedPattern('fraud_pattern', 'acc_777', ['alert' => [new Event('fraud', ['score' => 99])]], 100.0, 105.0);
$wfSink->emit($matchedMock);

$wfInstances = $wfEngine->getStore()->listInstances(null, 'fraud_investigation_process');
assertTest(count($wfInstances) === 1, "WorkflowStreamSink automatically triggered BPMN workflow instance");
assertTest($wfInstances[0]->getBusinessKey() === 'acc_777', "Workflow instance business key set to partition key");

// -----------------------------------------------------------------------------
// 7. CEPRule & RuleBuilder
// -----------------------------------------------------------------------------
echo "\n[7] Testing CEPRule & RuleBuilder...\n";

$alertsCaptured = [];
$rule = RuleBuilder::create('high_temperature_alert')
    ->description('Detects temperature above 50C')
    ->from('iot_sensor')
    ->where(fn(EventInterface $e) => (float)$e->get('temperature', 0) > 50.0)
    ->keyBy('sensor_id')
    ->then(function ($match, $ctx) use (&$alertsCaptured) {
        $alertsCaptured[] = ['match' => $match, 'ctx' => $ctx];
    })
    ->build();

assertTest($rule->getName() === 'high_temperature_alert', "Rule name matches");
assertTest($rule->getStreamName() === 'iot_sensor', "Rule stream matches");

$rule->process(new Event('iot_sensor', ['sensor_id' => 's1', 'temperature' => 45.0]));
assertTest(empty($alertsCaptured), "Rule filter discarded temperature 45.0");

$rule->process(new Event('iot_sensor', ['sensor_id' => 's1', 'temperature' => 55.0]));
assertTest(count($alertsCaptured) === 1, "Rule triggered on temperature 55.0");
assertTest($alertsCaptured[0]['ctx']['key'] === 's1', "Rule context partition key is 's1'");
assertTest($rule->getStats()['matches_detected'] === 1, "Rule stats show 1 match detected");

// -----------------------------------------------------------------------------
// 8. Stores (ArrayCEPStore & DatabaseCEPStore with SQLite)
// -----------------------------------------------------------------------------
echo "\n[8] Testing Array & Database CEP Persistence Stores...\n";

$arrayStore = new ArrayCEPStore();
$alertId = $arrayStore->saveAlert(['rule_name' => 'rule_1', 'stream' => 'orders', 'severity' => 'HIGH', 'payload' => ['foo' => 'bar']]);
assertTest(!empty($alertId), "ArrayCEPStore saved alert with generated ID");
$alerts = $arrayStore->getAlerts(['rule_name' => 'rule_1']);
assertTest(count($alerts) === 1, "ArrayCEPStore retrieved alert by filter");

$arrayStore->saveState('rule_1', 'k1', ['count' => 5]);
$state = $arrayStore->loadState('rule_1', 'k1');
assertTest($state['count'] === 5, "ArrayCEPStore loaded state snapshot");

// Test SQLite DatabaseCEPStore
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Run migration
$migration = require __DIR__ . '/../database/migrations/2026_09_21_000037_create_cep_engine_tables.php';
$migration->up($pdo);

$dbStore = new DatabaseCEPStore($pdo);
$dbAlertId = $dbStore->saveAlert([
    'rule_name' => 'sql_rule',
    'stream' => 'payments',
    'partition_key' => 'usr_99',
    'severity' => 'CRITICAL',
    'payload' => ['tx' => 'TX-888', 'amount' => 9999.0],
]);
assertTest(!empty($dbAlertId), "DatabaseCEPStore saved alert to SQLite");

$dbAlerts = $dbStore->getAlerts(['rule_name' => 'sql_rule']);
assertTest(count($dbAlerts) === 1, "DatabaseCEPStore retrieved alert from database");
assertTest($dbAlerts[0]['severity'] === 'CRITICAL', "Database alert severity matches");
assertTest($dbAlerts[0]['payload']['amount'] == 9999.0, "Database alert JSON payload deserialized correctly");

$dbStore->saveState('sql_rule', 'usr_99', ['window_start' => 100, 'count' => 42]);
$dbLoadedState = $dbStore->loadState('sql_rule', 'usr_99');
assertTest($dbLoadedState['count'] === 42, "DatabaseCEPStore loaded state snapshot from database");

$dbStore->recordMetric('events.rate', 1500.0, ['stream' => 'payments']);
$metricsCount = (int)$pdo->query("SELECT COUNT(*) FROM nf_cep_metrics")->fetchColumn();
assertTest($metricsCount === 1, "DatabaseCEPStore recorded metric into nf_cep_metrics");

// -----------------------------------------------------------------------------
// 9. Master CEPManager Facade & Global Helpers
// -----------------------------------------------------------------------------
echo "\n[9] Testing Master CEPManager Facade & Helpers...\n";

$cepManager = new CEPManager($dbStore);

// Fluent rule registration via manager
$cepManager->rule('payment_fraud')
    ->description('Fraud detection rule')
    ->from('payments')
    ->where(fn(EventInterface $e) => (float)$e->get('amount', 0) > 5000.0)
    ->keyBy('card_id')
    ->register();

assertTest($cepManager->getRule('payment_fraud') !== null, "CEPManager successfully registered rule via rule() builder");

$ingestResults = $cepManager->ingest([
    'type' => 'payments',
    'card_id' => 'CARD-1234',
    'amount' => 7500.0,
]);
assertTest(count($ingestResults) === 1, "CEPManager synchronous ingest triggered rule");

$stats = $cepManager->getStats();
assertTest($stats['total_events_ingested'] === 1, "CEPManager stats total_events_ingested is 1");
assertTest($stats['total_alerts_emitted'] === 1, "CEPManager stats total_alerts_emitted is 1");
assertTest($stats['registered_rules'] === 1, "CEPManager stats registered_rules is 1");

// Test Global Helpers
assertTest(function_exists('cep'), "Global helper cep() exists");
assertTest(function_exists('cep_event'), "Global helper cep_event() exists");
assertTest(function_exists('cep_ingest'), "Global helper cep_ingest() exists");
assertTest(function_exists('cep_rule'), "Global helper cep_rule() exists");

$helperEvt = cep_event('test_helper', ['x' => 1]);
assertTest($helperEvt instanceof Event && $helperEvt->getType() === 'test_helper', "cep_event() creates Event instance");

// -----------------------------------------------------------------------------
// 10. CLI Console Commands
// -----------------------------------------------------------------------------
echo "\n[10] Testing Console Commands Registration...\n";

$kernel = app()->make(Kernel::class);
$commands = $kernel->getCommands();

assertTest(isset($commands['cep:rules']), "Command 'cep:rules' is registered in Console Kernel");
assertTest(isset($commands['cep:ingest']), "Command 'cep:ingest' is registered in Console Kernel");
assertTest(isset($commands['cep:stats']), "Command 'cep:stats' is registered in Console Kernel");
assertTest(isset($commands['cep:alerts']), "Command 'cep:alerts' is registered in Console Kernel");

// Test Command Execution
ob_start();
$exitCode = $kernel->handle(['netfusion', 'cep:stats']);
$output = ob_get_clean();
assertTest($exitCode === 0, "Command 'cep:stats' executed with exit code 0");
assertTest(str_contains($output, 'CEP Streaming & Pattern Engine Runtime Stats'), "Command 'cep:stats' output header matched");

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n================================================================================\n";
echo "           PHASE 57 TEST SUMMARY: {$pass} PASSED, {$fail} FAILED\n";
echo "================================================================================\n";

if ($fail > 0) {
    exit(1);
}
