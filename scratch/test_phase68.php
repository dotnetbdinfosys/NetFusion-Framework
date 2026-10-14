<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Phase 68 Unit Test Suite
 |--------------------------------------------------------------------------
 |
 | Covers all 5 sub-modules:
 |   68A: Double-Entry Accounting Engine
 |   68B: Push Notification Gateway
 |   68C: Log Aggregation & APM Engine
 |   68D: Multi-Region Data Replication Engine
 |   68E: BPMN 2.0 Extensions (Compensation + DMN)
 |
 | Run: D:\SERVER\bin\php\php8.2.29\php.exe scratch/test_phase68.php
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Autoload
spl_autoload_register(function (string $class): void {
    $map = [
        'NetFusion\\Core\\' => BASE_PATH . '/core/',
    ];
    foreach ($map as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $base . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
    $vendor = BASE_PATH . '/vendor/autoload.php';
    if (file_exists($vendor)) require_once $vendor;
});

// ---- Test Helpers ----
$passed = 0;
$failed = 0;
$errors = [];

function assertTest(bool $condition, string $label): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo "  ✓ {$label}\n";
        $passed++;
    } else {
        echo "  ✗ {$label}\n";
        $failed++;
        $errors[] = $label;
    }
}

function section(string $title): void {
    echo "\n═══ {$title} ═══\n";
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║       NetFusion Phase 68 — Enterprise Platform Suite        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

// =============================================================================
// MODULE 68A: Double-Entry Accounting Engine
// =============================================================================

use NetFusion\Core\Accounting\Models\Money;
use NetFusion\Core\Accounting\Models\Account;
use NetFusion\Core\Accounting\Models\JournalEntry;
use NetFusion\Core\Accounting\ChartOfAccounts;
use NetFusion\Core\Accounting\JournalLedger;
use NetFusion\Core\Accounting\TrialBalance;
use NetFusion\Core\Accounting\IncomeStatement;
use NetFusion\Core\Accounting\BalanceSheet;
use NetFusion\Core\Accounting\ClosingEntries;
use NetFusion\Core\Accounting\AccountingManager;
use NetFusion\Core\Accounting\Exceptions\UnbalancedJournalEntryException;

section('68A-1: Money — BCMath Arithmetic');

$m1 = Money::of('100.00');
$m2 = Money::of('50.00');
assertTest($m1->add($m2)->equals(Money::of('150.00')),        'add: 100+50=150');
assertTest($m1->subtract($m2)->equals(Money::of('50.00')),    'subtract: 100-50=50');
assertTest($m1->multiply('0.10')->equals(Money::of('10.00')), 'multiply: 100*0.10=10');
assertTest(Money::zero()->isZero(),                            'zero() is zero');
assertTest($m1->isPositive(),                                  '100 is positive');
assertTest(Money::of('-5')->isNegative(),                      '-5 is negative');
assertTest(Money::of('100.00')->equals(Money::of('100.000000')), 'BCMath precision equality');

$threw = false;
try { $m1->add(Money::of('10', 'EUR')); } catch (\InvalidArgumentException) { $threw = true; }
assertTest($threw, 'currency mismatch throws InvalidArgumentException');

section('68A-2: Account — Types and Normal Balances');

$cash   = Account::asset('1001', 'Cash');
$loan   = Account::liability('2001', 'Loan Payable');
$equity = Account::equity('3001', 'Common Stock');
$rev    = Account::income('4001', 'Sales Revenue');
$exp    = Account::expense('5001', 'Rent Expense');

assertTest($cash->isAsset(),     'Cash is asset');
assertTest($loan->isLiability(), 'Loan is liability');
assertTest($equity->isEquity(),  'Common Stock is equity');
assertTest($rev->isIncome(),     'Sales Revenue is income');
assertTest($exp->isExpense(),    'Rent Expense is expense');
assertTest($cash->getNormalBalance() === 'debit',    'Asset normal balance = debit');
assertTest($loan->getNormalBalance() === 'credit',   'Liability normal balance = credit');
assertTest($rev->getNormalBalance() === 'credit',    'Income normal balance = credit');
assertTest($exp->getNormalBalance() === 'debit',     'Expense normal balance = debit');

section('68A-3: JournalEntry — Balanced and Unbalanced');

// Balanced entry
$entry = JournalEntry::create('2026-01-01', 'Initial investment', 'JE-001')
    ->debit('1001', Money::of('50000.00'))
    ->credit('3001', Money::of('50000.00'));

assertTest($entry->getTotalDebits()->equals(Money::of('50000.00')), 'JournalEntry: total debits = 50000');
assertTest($entry->getTotalCredits()->equals(Money::of('50000.00')),'JournalEntry: total credits = 50000');
assertTest($entry->touchesAccount('1001'),                          'touchesAccount(1001) = true');
assertTest(!$entry->touchesAccount('9999'),                         'touchesAccount(9999) = false');

// Unbalanced entry throws
$threw = false;
try {
    JournalEntry::create('2026-01-01', 'Bad entry')
        ->debit('1001', Money::of('100'))
        ->credit('3001', Money::of('90'))
        ->validate();
} catch (UnbalancedJournalEntryException) {
    $threw = true;
}
assertTest($threw, 'Unbalanced entry throws UnbalancedJournalEntryException');

section('68A-4: ChartOfAccounts — Registry and Seed');

$coa = new ChartOfAccounts();
$coa->seedDefaults();

assertTest($coa->count() >= 10,             'seedDefaults() registers ≥ 10 accounts');
assertTest($coa->find('1001') !== null,      'Cash (1001) found after seed');
assertTest($coa->find('4001') !== null,      'Sales Revenue (4001) found after seed');
assertTest(count($coa->assets()) > 0,        'assets() returns accounts');
assertTest(count($coa->income()) > 0,        'income() returns accounts');
assertTest(count($coa->expenses()) > 0,      'expenses() returns accounts');

section('68A-5: JournalLedger — Post and Balance');

$coa2   = new ChartOfAccounts();
$coa2->seedDefaults();
$ledger = new JournalLedger($coa2);

// Investment: debit Cash 50000, credit Common Stock 50000
$inv = JournalEntry::create('2026-01-01', 'Initial investment')
    ->debit('1001', Money::of('50000.00'))
    ->credit('3001', Money::of('50000.00'));
$ledger->post($inv);

// Revenue: debit Cash 10000, credit Sales Revenue 10000
$sale = JournalEntry::create('2026-01-01', 'First sale')
    ->debit('1001', Money::of('10000.00'))
    ->credit('4001', Money::of('10000.00'));
$ledger->post($sale);

// Expense: debit Rent 2000, credit Cash 2000
$rent = JournalEntry::create('2026-01-01', 'January rent')
    ->debit('5001', Money::of('2000.00'))
    ->credit('1001', Money::of('2000.00'));
$ledger->post($rent);

$cashBal = $ledger->getBalance('1001');   // 50000 + 10000 - 2000 = 58000
$revBal  = $ledger->getBalance('4001');   // credit: 10000
$expBal  = $ledger->getBalance('5001');   // debit:  2000

assertTest($cashBal->equals(Money::of('58000.00')),  'Cash balance: 58000');
assertTest($revBal->equals(Money::of('10000.00')),   'Revenue balance: 10000');
assertTest($expBal->equals(Money::of('2000.00')),    'Rent expense balance: 2000');
assertTest($ledger->countEntries() === 3,            'ledger has 3 entries');

section('68A-6: TrialBalance');

$tb = new TrialBalance($ledger, '2026-12-31');
assertTest($tb->isBalanced(),                  'Trial balance is balanced');
$arr = $tb->toArray();
assertTest(isset($arr['balanced']),            'toArray() has balanced key');
assertTest($arr['balanced'] === true,          'toArray()[balanced] = true');
$rendered = $tb->render();
assertTest(str_contains($rendered, 'TRIAL BALANCE'), 'render() contains TRIAL BALANCE header');

section('68A-7: IncomeStatement');

$is = new IncomeStatement($ledger, '2026-01-01', '2026-12-31');
assertTest($is->getTotalRevenue()->equals(Money::of('10000.00')),  'IS: revenue = 10000');
assertTest($is->getTotalExpense()->equals(Money::of('2000.00')),   'IS: expenses = 2000');
assertTest($is->getNetIncome()->equals(Money::of('8000.00')),      'IS: net income = 8000');
assertTest($is->isProfitable(),                                    'IS: profitable = true');
assertTest(str_contains($is->render(), 'NET INCOME'),              'IS: render() has NET INCOME');

section('68A-8: BalanceSheet');

$bs = new BalanceSheet($ledger, '2026-12-31');
assertTest($bs->isBalanced(),                       'BalanceSheet: A = L + E (balanced)');
assertTest(!$bs->getTotalAssets()->isZero(),        'BalanceSheet: totalAssets > 0');
assertTest(str_contains($bs->render(), 'ASSETS'),   'BalanceSheet: render() has ASSETS');

section('68A-9: ClosingEntries');

$coa3   = new ChartOfAccounts();
$coa3->seedDefaults();
$led3   = new JournalLedger($coa3);

$led3->post(JournalEntry::create('2026-01-01', 'Revenue')
    ->debit('1001', Money::of('5000'))
    ->credit('4001', Money::of('5000')));
$led3->post(JournalEntry::create('2026-01-01', 'Expense')
    ->debit('5001', Money::of('1000'))
    ->credit('1001', Money::of('1000')));

$closing = new ClosingEntries($led3, '2026-12-31');
$entries = $closing->close();
assertTest(count($entries) >= 2,  'ClosingEntries: at least 2 entries posted');

// After closing, income/expense accounts should be zeroed
$revAfter = $led3->getBalance('4001');
$expAfter = $led3->getBalance('5001');
assertTest($revAfter->isZero(),   'Revenue zero after closing');
assertTest($expAfter->isZero(),   'Expense zero after closing');

section('68A-10: AccountingManager');

AccountingManager::resetInstance();
$mgr = AccountingManager::create();
$mgr->getCoa()->seedDefaults();

$mgr->post(JournalEntry::create('2026-01-01', 'Capital injection')
    ->debit('1001', Money::of('100000'))
    ->credit('3001', Money::of('100000')));

assertTest(!$mgr->balance('1001')->isZero(),     'AccountingManager: cash balance > 0 after posting');
assertTest($mgr->trialBalance('2026-12-31')->isBalanced(), 'AccountingManager: trial balance balanced');
assertTest($mgr->getStats()['total_accounts'] > 0,         'AccountingManager: stats ok');

// =============================================================================
// MODULE 68B: Push Notification Gateway
// =============================================================================

use NetFusion\Core\Notification\Notification;
use NetFusion\Core\Notification\NotificationManager;
use NetFusion\Core\Notification\NotificationLog;
use NetFusion\Core\Notification\TopicManager;
use NetFusion\Core\Notification\Channels\FcmChannel;
use NetFusion\Core\Notification\Channels\ApnsChannel;
use NetFusion\Core\Notification\Channels\WebPushChannel;
use NetFusion\Core\Notification\Channels\DatabaseChannel;

section('68B-1: Notification Payload Builder');

$notif = Notification::create()
    ->withTitle('Test Alert')
    ->withBody('Hello World')
    ->withData(['key' => 'value'])
    ->withBadge(5)
    ->withTtl(7200)
    ->withPriority('normal');

assertTest($notif->getTitle() === 'Test Alert',  'Notification: title set');
assertTest($notif->getBody() === 'Hello World',  'Notification: body set');
assertTest($notif->getBadge() === 5,             'Notification: badge = 5');
assertTest($notif->getTtl() === 7200,            'Notification: TTL = 7200');
assertTest(!empty($notif->getId()),              'Notification: has ID');
$arr = $notif->toArray();
assertTest(isset($arr['title'], $arr['body']),   'Notification: toArray() keys present');

// FCM payload
$fcmPayload = $notif->toFcmPayload('device_token_123');
assertTest(isset($fcmPayload['message']['notification']),  'FCM payload: notification key present');

section('68B-2: Channels — Simulate Mode');

$fcm   = new FcmChannel(simulate: true);
$apns  = new ApnsChannel(simulate: true);
$wpush = new WebPushChannel(simulate: true);
$db    = new DatabaseChannel();

assertTest($fcm->getName() === 'fcm',           'FcmChannel: name = fcm');
assertTest($apns->getName() === 'apns',         'ApnsChannel: name = apns');
assertTest($wpush->getName() === 'web_push',    'WebPushChannel: name = web_push');
assertTest($db->getName() === 'database',       'DatabaseChannel: name = database');

assertTest($fcm->isAvailable(),                 'FCM simulate mode: isAvailable = true');
assertTest($apns->isAvailable(),                'APNs simulate mode: isAvailable = true');

$res = $fcm->send('tok123', $notif);
assertTest($res['success'] === true,            'FCM simulate: send returns success=true');
assertTest(!empty($res['message_id']),          'FCM simulate: has message_id');

$res2 = $apns->send('device_token', $notif);
assertTest($res2['success'] === true,           'APNs simulate: send returns success=true');

section('68B-3: NotificationManager — Send and Log');

DatabaseChannel::clearMemoryStore();
$nm = NotificationManager::create();
$nm->addChannel(new FcmChannel(simulate: true))
   ->addChannel(new DatabaseChannel())
   ->setDefaultChannel('fcm');

$result = $nm->send('user_device_1', $notif);
assertTest($result['success'] === true,         'NotificationManager: send success');
assertTest($nm->getLog()->total() === 1,        'NotificationManager: log has 1 entry');

// Batch
$results = $nm->sendBatch(['tok1', 'tok2', 'tok3'], $notif);
assertTest(count($results) === 3,               'NotificationManager: batch sends to 3 recipients');

section('68B-4: TopicManager — Subscribe and Broadcast');

$nm2 = NotificationManager::create();
$nm2->addChannel(new DatabaseChannel())->setDefaultChannel('database');
$nm2->subscribe('breaking-news', 'user1');
$nm2->subscribe('breaking-news', 'user2');
$nm2->subscribe('breaking-news', 'user3');

assertTest($nm2->getTopics()->countSubscribers('breaking-news') === 3, 'TopicManager: 3 subscribers');

$bcast = $nm2->broadcastToTopic('breaking-news', $notif);
assertTest(count($bcast) === 3,                 'broadcastToTopic: 3 results');
assertTest($bcast['user1']['success'] === true, 'broadcastToTopic: user1 success');

$nm2->unsubscribe('breaking-news', 'user2');
assertTest($nm2->getTopics()->countSubscribers('breaking-news') === 2, 'TopicManager: 2 after unsubscribe');

section('68B-5: NotificationLog — Statistics');

$log = new NotificationLog();
$log->record('fcm', 'u1', 'n1', true, 'msg_1');
$log->record('apns', 'u2', 'n2', false, null, 'Connection refused');
$log->record('database', 'u3', 'n3', true, 'db_1');

assertTest($log->countSuccess() === 2,         'Log: 2 successes');
assertTest($log->countFailure() === 1,         'Log: 1 failure');
assertTest($log->total() === 3,                'Log: 3 total');
assertTest(count($log->getLogs('unknown')) === 0, 'Log: getLogs(unknown) = 0');
assertTest(count($log->getLogs('fcm')) === 1,  'Log: getLogs(fcm) = 1');

// =============================================================================
// MODULE 68C: APM Engine
// =============================================================================

use NetFusion\Core\Apm\Logging\StructuredLogger;
use NetFusion\Core\Apm\Tracing\SpanContext;
use NetFusion\Core\Apm\Tracing\Span;
use NetFusion\Core\Apm\Tracing\Tracer;
use NetFusion\Core\Apm\Profiling\Profiler;
use NetFusion\Core\Apm\Profiling\SlowQueryDetector;
use NetFusion\Core\Apm\ApmManager;

section('68C-1: StructuredLogger — Levels and Buffer');

$logger = new StructuredLogger('test', 'debug');
$logger->info('Server started', ['port' => 8080]);
$logger->warning('High memory usage', ['mb' => 512]);
$logger->error('DB connection failed');

assertTest($logger->count() === 3,                             'Logger: 3 records in buffer');
assertTest(count($logger->getByLevel('info')) === 1,           'Logger: 1 INFO record');
assertTest(count($logger->getByLevel('error')) === 1,          'Logger: 1 ERROR record');

$logger->withContext(['env' => 'test'])->info('Enriched log');
assertTest($logger->count() === 4,                             'Logger: withContext adds to buffer');

// Level filtering
$warnOnly = new StructuredLogger('warn-test', 'warning');
$warnOnly->debug('Ignored debug');
$warnOnly->info('Ignored info');
$warnOnly->warning('Visible warning');
assertTest($warnOnly->count() === 1,                           'Logger: debug/info filtered below warning level');

section('68C-2: SpanContext — W3C TraceContext');

$ctx = SpanContext::generate();
assertTest(strlen($ctx->getTraceId()) === 32,  'SpanContext: trace_id is 32 hex chars');
assertTest(strlen($ctx->getSpanId()) === 16,   'SpanContext: span_id is 16 hex chars');
assertTest($ctx->isSampled(),                  'SpanContext: default flags = sampled');

$tp = $ctx->toTraceparent();
assertTest(str_starts_with($tp, '00-'),        'SpanContext: traceparent starts with 00-');

$reparsed = SpanContext::fromTraceparent($tp);
assertTest($reparsed !== null,                 'SpanContext: fromTraceparent parses correctly');
assertTest($reparsed->getTraceId() === $ctx->getTraceId(), 'SpanContext: round-trip trace_id matches');

$child = SpanContext::child($ctx);
assertTest($child->getTraceId() === $ctx->getTraceId(), 'SpanContext: child shares trace_id');
assertTest($child->getSpanId() !== $ctx->getSpanId(),   'SpanContext: child has different span_id');

section('68C-3: Span — Lifecycle and Timing');

$spanCtx = SpanContext::generate();
$span    = new Span('http.request', $spanCtx);
$span->setTag('method', 'GET');
$span->setTag('url', '/api/users');
$span->addEvent('request.received');
usleep(1000); // 1ms
$span->finish();

assertTest($span->isFinished(),                         'Span: isFinished after finish()');
assertTest($span->getDurationMs() >= 0,                 'Span: duration >= 0ms');
assertTest($span->getStatus() === 'ok',                 'Span: default status = ok');
assertTest($span->getTags()['method'] === 'GET',        'Span: tag method = GET');
assertTest(count($span->getEvents()) === 1,             'Span: 1 event recorded');

$errSpan = new Span('db.query', SpanContext::generate());
$errSpan->error('Timeout exceeded')->finish();
assertTest($errSpan->getStatus() === 'error',           'Span: error status set');
assertTest($errSpan->getTags()['error'] === true,       'Span: error tag = true');

section('68C-4: Tracer — Distributed Tracing');

$tracer = new Tracer('test-service');

// trace() wrapper
$result = $tracer->trace('computation', function(Span $s) {
    $s->setTag('result_type', 'integer');
    return 42;
});
assertTest($result === 42,                              'Tracer: trace() returns callable result');
assertTest(count($tracer->getCompletedSpans()) === 1,  'Tracer: 1 completed span');

// Traceparent propagation
$incoming = SpanContext::generate();
$span2 = $tracer->startSpan('downstream', $incoming->toTraceparent());
assertTest($span2->getTraceId() === $incoming->getTraceId(), 'Tracer: incoming traceparent links trace');
$tracer->finishSpan($span2);

// Exception handling
$threw = false;
try {
    $tracer->trace('failing-op', function(Span $s) {
        throw new \RuntimeException('Simulated failure');
    });
} catch (\RuntimeException) { $threw = true; }
assertTest($threw,                                     'Tracer: exception propagates out of trace()');
assertTest(count($tracer->getCompletedSpans()) === 3,  'Tracer: error span also recorded');

section('68C-5: Profiler');

$profiler = new Profiler('app-boot');
$profiler->checkpoint('config');
usleep(500);
$profiler->checkpoint('routes');
$profiler->stop();

assertTest(count($profiler->getCheckpoints()) === 2,   'Profiler: 2 checkpoints recorded');
assertTest($profiler->elapsedMs() >= 0,               'Profiler: elapsed time >= 0');
assertTest($profiler->getSlowest() !== null,           'Profiler: slowest checkpoint set');
assertTest(str_contains($profiler->render(), 'Profiler'), 'Profiler: render() contains header');

section('68C-6: SlowQueryDetector');

$sqd = new SlowQueryDetector(50.0); // 50ms threshold
$sqd->record('SELECT 1', 10.0);
$sqd->record('SELECT * FROM users', 120.0);
$sqd->record('SELECT * FROM orders', 75.0);

assertTest($sqd->getCount() === 3,                     'SlowQuery: 3 queries recorded');
assertTest($sqd->getSlowCount() === 2,                 'SlowQuery: 2 slow queries (>50ms)');
assertTest($sqd->hasSlow(),                            'SlowQuery: hasSlow() = true');
assertTest($sqd->getAverageMs() > 0,                   'SlowQuery: average > 0');

section('68C-7: ApmManager');

$apm = ApmManager::create('testing');
$apm->event('user.login', ['user_id' => 42]);
$apm->recordQuery('SELECT * FROM heavy_table', 200.0);
$profile = $apm->profile('api-request');

assertTest($apm->logger()->count() >= 2,              'ApmManager: ≥2 log entries (event + slow query warning)');
assertTest($apm->slowQuery()->getSlowCount() === 1,   'ApmManager: 1 slow query recorded');
assertTest($apm->getEnvironment() === 'testing',      'ApmManager: environment = testing');
$summary = $apm->getSummary();
assertTest(isset($summary['log_count'], $summary['span_count']), 'ApmManager: getSummary() has keys');

// =============================================================================
// MODULE 68D: Multi-Region Data Replication Engine
// =============================================================================

use NetFusion\Core\Replication\CRDTs\GCounter;
use NetFusion\Core\Replication\CRDTs\PNCounter;
use NetFusion\Core\Replication\CRDTs\LWWRegister;
use NetFusion\Core\Replication\CRDTs\ORSet;
use NetFusion\Core\Replication\CRDTs\MVRegister;
use NetFusion\Core\Replication\VectorClock;
use NetFusion\Core\Replication\ReplicationManager;

section('68D-1: GCounter CRDT');

$gc1 = new GCounter();
$gc1 = $gc1->increment('r1', 3)->increment('r2', 2);
assertTest($gc1->value() === 5,                  'GCounter: value = 5 (r1:3 + r2:2)');

$gc2 = new GCounter(['r1' => 5, 'r3' => 1]);
$merged = $gc1->merge($gc2);
assertTest($merged->value() === 8,               'GCounter: merge value = 8 (max r1:5, r2:2, r3:1)');
assertTest($merged->get('r1') === 5,             'GCounter: merged r1 = 5 (max of 3,5)');

$restored = GCounter::fromArray($gc1->toArray());
assertTest($restored->value() === $gc1->value(), 'GCounter: serialize/deserialize preserves value');

section('68D-2: PNCounter CRDT');

$pn = new PNCounter();
$pn = $pn->increment('r1', 10)->decrement('r1', 3)->increment('r2', 5);
assertTest($pn->value() === 12,                  'PNCounter: value = 12 (15-3)');

$pn2 = new PNCounter();
$pn2 = $pn2->decrement('r1', 2);
$merged2 = $pn->merge($pn2);
assertTest($merged2->value() <= 12,              'PNCounter: merge respects both P and N');

section('68D-3: LWWRegister CRDT');

$lww = new LWWRegister();
$lww = $lww->set('hello', 'r1', 1000.0)
           ->set('world', 'r2', 2000.0);    // newer timestamp wins

assertTest($lww->get() === 'world',              'LWWRegister: higher timestamp wins');

$older = new LWWRegister();
$older = $older->set('old', 'r1', 500.0);
$merged3 = $lww->merge($older);
assertTest($merged3->get() === 'world',          'LWWRegister: merge keeps winner (world)');

// Tiebreaker: higher regionId wins
$tie1 = (new LWWRegister())->set('A', 'region-1', 1000.0);
$tie2 = (new LWWRegister())->set('B', 'region-2', 1000.0);
$tieMerged = $tie1->merge($tie2);
assertTest($tieMerged->get() === 'B',            'LWWRegister: tiebreak by regionId (region-2 > region-1)');

section('68D-4: ORSet CRDT (Add-Wins)');

$set = new ORSet();
$set = $set->add('apple')->add('banana')->add('cherry');
assertTest($set->contains('apple'),              'ORSet: contains apple');
assertTest($set->count() === 3,                  'ORSet: 3 elements');

$set2 = $set->remove('banana');
assertTest(!$set2->contains('banana'),           'ORSet: banana removed');
assertTest($set2->count() === 2,                 'ORSet: 2 after remove');

// Add-wins: concurrent add + remove
$setA = $set->remove('apple');  // Remove in replica A
$setB = $set->add('apple');     // Re-add in replica B (concurrent)
$concMerge = $setA->merge($setB);
// Since setB has a newer add tag, apple should be present
assertTest($concMerge->contains('apple'),        'ORSet: add-wins on concurrent add+remove');

section('68D-5: VectorClock');

$vc1 = new VectorClock();
$vc1 = $vc1->increment('n1')->increment('n1')->increment('n2');
assertTest($vc1->get('n1') === 2,                'VectorClock: n1 = 2');
assertTest($vc1->get('n2') === 1,                'VectorClock: n2 = 1');

$vc2 = new VectorClock(['n1' => 1, 'n2' => 3]);
$merged4 = $vc1->merge($vc2);
assertTest($merged4->get('n1') === 2,            'VectorClock: merge n1 = max(2,1) = 2');
assertTest($merged4->get('n2') === 3,            'VectorClock: merge n2 = max(1,3) = 3');

// Causality
$c1 = new VectorClock(['n1' => 1]);
$c2 = new VectorClock(['n1' => 2]);
assertTest($c1->happensBefore($c2),              'VectorClock: c1 happens-before c2');
assertTest(!$c2->happensBefore($c1),             'VectorClock: c2 does NOT happen-before c1');

$c3 = new VectorClock(['n1' => 2, 'n2' => 0]);
$c4 = new VectorClock(['n1' => 0, 'n2' => 2]);
assertTest($c3->isConcurrent($c4),               'VectorClock: c3 and c4 are concurrent');

section('68D-6: MVRegister CRDT');

$mv = new MVRegister();
$mv = $mv->set('v1', 'n1');
assertTest($mv->get() === ['v1'],                'MVRegister: single value');
assertTest(!$mv->isConcurrent(),                 'MVRegister: not concurrent (single write)');

section('68D-7: ReplicationManager');

$rm = ReplicationManager::create('us-east-1');
assertTest($rm->getLocalRegion() === 'us-east-1', 'ReplicationManager: region set');

$rm->incrementGCounter('page_views', 5);
assertTest($rm->gCounter('page_views')->value() === 5, 'ReplicationManager: GCounter incremented');

$rm->incrementPNCounter('stock', 100);
$rm->decrementPNCounter('stock', 30);
assertTest($rm->pnCounter('stock')->value() === 70, 'ReplicationManager: PNCounter = 70');

$rm->addToSet('active_users', 'user1');
$rm->addToSet('active_users', 'user2');
$rm->removeFromSet('active_users', 'user1');
assertTest(!$rm->orSet('active_users')->contains('user1'), 'ReplicationManager: user1 removed from set');
assertTest($rm->orSet('active_users')->contains('user2'), 'ReplicationManager: user2 still in set');

$rm->setLww('config.theme', 'dark');
assertTest($rm->lwwRegister('config.theme')->get() === 'dark', 'ReplicationManager: LWW register set');

$stats = $rm->getStats();
assertTest(isset($stats['region']),              'ReplicationManager: getStats() has region');

// =============================================================================
// MODULE 68E: BPMN 2.0 Extensions
// =============================================================================

use NetFusion\Core\Workflow\Compensation\CompensationHandler;
use NetFusion\Core\Workflow\Compensation\CompensationCoordinator;
use NetFusion\Core\Workflow\DMN\DecisionTable;
use NetFusion\Core\Workflow\DMN\DmnEvaluator;

section('68E-1: CompensationHandler');

$compensated = false;
$handler = new CompensationHandler(
    'charge_card',
    function (array $output, array $context) use (&$compensated): array {
        $compensated = true;
        return ['refunded' => $output['charge_id']];
    },
    ['charge_id' => 'ch_abc123']
);

assertTest($handler->getActivityId() === 'charge_card',  'CompensationHandler: activityId set');
$result = $handler->compensate([]);
assertTest($compensated === true,                        'CompensationHandler: compensate() executed');
assertTest($result['refunded'] === 'ch_abc123',          'CompensationHandler: returned correct refund id');

section('68E-2: CompensationCoordinator — LIFO Order');

$log68e = [];
$coord  = new CompensationCoordinator();

// Register 3 activities (in completion order: A → B → C)
$coord->register('activity_A', new CompensationHandler('activity_A', function() use (&$log68e) {
    $log68e[] = 'undo_A';
    return [];
}, []));
$coord->register('activity_B', new CompensationHandler('activity_B', function() use (&$log68e) {
    $log68e[] = 'undo_B';
    return [];
}, []));
$coord->register('activity_C', new CompensationHandler('activity_C', function() use (&$log68e) {
    $log68e[] = 'undo_C';
    return [];
}, []));

assertTest($coord->count() === 3,                        'CompensationCoordinator: 3 handlers registered');
assertTest($coord->hasHandler('activity_B'),             'CompensationCoordinator: activity_B found');

$results2 = $coord->compensate([]);
assertTest($coord->wasTriggered(),                       'CompensationCoordinator: triggered = true');
assertTest(count($results2) === 3,                       'CompensationCoordinator: 3 results returned');
// LIFO: C → B → A
assertTest($log68e === ['undo_C', 'undo_B', 'undo_A'],   'CompensationCoordinator: LIFO order correct');

section('68E-3: DMN Decision Table — FIRST hit policy');

$loanTable = new DecisionTable(
    ['credit_score', 'amount'],
    ['approved', 'interest_rate'],
    'FIRST',
    'loan_approval'
);

$loanTable
    ->addRule(['credit_score' => fn($s) => $s >= 750, 'amount' => fn($a) => $a <= 50000],
              ['approved' => true,  'interest_rate' => 0.05])
    ->addRule(['credit_score' => fn($s) => $s >= 600, 'amount' => null],
              ['approved' => true,  'interest_rate' => 0.12])
    ->addRule(['credit_score' => null, 'amount' => null],
              ['approved' => false, 'interest_rate' => null]);

$r1 = $loanTable->evaluate(['credit_score' => 780, 'amount' => 30000]);
assertTest($r1 !== null,                               'DMN FIRST: high credit, small loan matches');
assertTest($r1['approved'] === true,                   'DMN FIRST: approved = true');
assertTest($r1['interest_rate'] === 0.05,              'DMN FIRST: rate = 0.05 (best tier)');

$r2 = $loanTable->evaluate(['credit_score' => 650, 'amount' => 100000]);
assertTest($r2['approved'] === true,                   'DMN FIRST: medium credit, large loan → approved');
assertTest($r2['interest_rate'] === 0.12,              'DMN FIRST: rate = 0.12 (middle tier)');

$r3 = $loanTable->evaluate(['credit_score' => 400, 'amount' => 5000]);
assertTest($r3['approved'] === false,                  'DMN FIRST: poor credit → rejected');

section('68E-4: DMN Decision Table — COLLECT hit policy');

$riskTable = new DecisionTable(
    ['country', 'age'],
    ['flag'],
    'COLLECT',
    'risk_flags'
);

$riskTable
    ->addRule(['country' => 'US', 'age' => null],   ['flag' => 'domestic'])
    ->addRule(['country' => null, 'age' => fn($a) => $a < 18], ['flag' => 'minor'])
    ->addRule(['country' => 'US', 'age' => fn($a) => $a >= 21], ['flag' => 'adult_us']);

$flags = $riskTable->evaluate(['country' => 'US', 'age' => 25]);
assertTest(is_array($flags),                          'DMN COLLECT: returns array of all matches');
assertTest(count($flags) >= 2,                        'DMN COLLECT: ≥2 rules matched for adult US');

section('68E-5: DmnEvaluator');

$evaluator = new DmnEvaluator();
$evaluator->register('loan_approval', $loanTable)
          ->register('risk_flags', $riskTable);

assertTest($evaluator->has('loan_approval'),           'DmnEvaluator: loan_approval registered');
assertTest($evaluator->has('risk_flags'),              'DmnEvaluator: risk_flags registered');

$out = $evaluator->decide('loan_approval', ['credit_score' => 800, 'amount' => 10000]);
assertTest($out !== null,                              'DmnEvaluator: decide() returns result');
assertTest($out['approved'] === true,                  'DmnEvaluator: approve excellent credit');
assertTest(count($evaluator->getAuditLog()) === 1,     'DmnEvaluator: 1 audit entry');

// Chain evaluation
$ctx = $evaluator->chain(['loan_approval', 'risk_flags'], ['credit_score' => 800, 'amount' => 10000, 'country' => 'US', 'age' => 30]);
assertTest(array_key_exists('credit_score', $ctx),     'DmnEvaluator: chain() retains original context');

$threw2 = false;
try { $evaluator->decide('nonexistent', []); } catch (\InvalidArgumentException) { $threw2 = true; }
assertTest($threw2,                                    'DmnEvaluator: unknown table throws InvalidArgumentException');

// =============================================================================
// SUMMARY
// =============================================================================

$total = $passed + $failed;
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    PHASE 68 TEST SUMMARY                    ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
printf("║  Passed: %-3d   Failed: %-3d   Total: %-3d                   ║\n", $passed, $failed, $total);
echo "╚══════════════════════════════════════════════════════════════╝\n";

if ($failed > 0) {
    echo "\nFailed assertions:\n";
    foreach ($errors as $err) {
        echo "  ✗ {$err}\n";
    }
    exit(1);
}
echo "\n✅ All Phase 68 tests passed!\n\n";
exit(0);
