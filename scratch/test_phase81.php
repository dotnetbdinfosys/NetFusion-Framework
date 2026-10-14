<?php

declare(strict_types=1);

/**
 * Phase 81 Test Suite: Enterprise Cloud-Native Distributed Key-Value Store (etcd v3 Architecture)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\Etcd\Enums\CompareResult;
use NetFusion\Core\Etcd\Enums\CompareTarget;
use NetFusion\Core\Etcd\Enums\EventType;
use NetFusion\Core\Etcd\EtcdManager;
use NetFusion\Core\Etcd\Exceptions\CompactedRevisionException;
use NetFusion\Core\Etcd\Models\WatchEvent;
use NetFusion\Core\Etcd\Storage\MvccStore;
use NetFusion\Core\Etcd\Txn\CompareOp;
use NetFusion\Core\Etcd\Txn\TxnOp;

$app = new Application(dirname(__DIR__));
$app->boot();

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}: {$details}\n";
    }
}

echo "=== Testing Phase 81: etcd v3 MVCC Distributed Key-Value Store ===\n\n";

$etcd = new EtcdManager();

// Test 1: Monotonic Revisions & Sequential Updates
$initialRev = $etcd->revision();
$kv1 = $etcd->put('/config/app/title', 'NetFusion');
$kv2 = $etcd->put('/config/app/title', 'NetFusion Enterprise');
assertTest(
    'Monotonic Revisions & Sequential Updates',
    $kv1->createRevision === $initialRev + 1 &&
    $kv1->version === 1 &&
    $kv2->createRevision === $kv1->createRevision &&
    $kv2->modRevision === $initialRev + 2 &&
    $kv2->version === 2 &&
    $etcd->revision() === $initialRev + 2,
    "Initial: {$initialRev}, Rev1: {$kv1->modRevision}, Rev2: {$kv2->modRevision}, Cur: {$etcd->revision()}"
);

// Test 2: Point-in-time Time-Travel Queries
$historicalAtRev1 = $etcd->get('/config/app/title', $kv1->modRevision);
$currentVal = $etcd->get('/config/app/title');
assertTest(
    'Point-in-time Time-Travel Query',
    $historicalAtRev1 !== null &&
    $historicalAtRev1->value === 'NetFusion' &&
    $currentVal !== null &&
    $currentVal->value === 'NetFusion Enterprise',
    "Hist: " . ($historicalAtRev1?->value ?? 'null') . ", Curr: " . ($currentVal?->value ?? 'null')
);

// Test 3: Tombstone Deletion & Historical Invisibility
$deleteRev = $etcd->delete('/config/app/title');
$afterDelete = $etcd->get('/config/app/title');
$history = $etcd->getHistory('/config/app/title');
assertTest(
    'Tombstone Deletion & History Preservation',
    $deleteRev !== null &&
    $deleteRev->isDeleted() &&
    $afterDelete === null &&
    count($history) === 3 &&
    $history[2]->isDeleted(),
    "After delete: " . ($afterDelete ? 'found' : 'null') . ", History count: " . count($history)
);

// Test 4: Prefix Range Queries
$etcd->put('/services/auth/node-1', '10.0.0.1:8080');
$etcd->put('/services/auth/node-2', '10.0.0.2:8080');
$etcd->put('/services/billing/node-1', '10.0.1.1:8080');
$rangeResults = $etcd->range('/services/auth/');
$allServices = $etcd->range('/services/');
assertTest(
    'Prefix Range Queries',
    count($rangeResults) === 2 &&
    isset($rangeResults['/services/auth/node-1']) &&
    isset($rangeResults['/services/auth/node-2']) &&
    count($allServices) === 3,
    "Auth nodes: " . count($rangeResults) . ", All nodes: " . count($allServices)
);

// Test 5: Range Deletion
$deletedCount = $etcd->deleteRange('/services/auth/');
$rangeAfterDelete = $etcd->range('/services/auth/');
assertTest(
    'Range Deletion by Prefix',
    $deletedCount === 2 && count($rangeAfterDelete) === 0,
    "Deleted: {$deletedCount}, Remaining: " . count($rangeAfterDelete)
);

// Test 6: MVCC Compaction & Expired Revision Rejection
$revBeforeCompact = $etcd->revision();
$etcd->put('/compaction/test', 'val1');
$revCompactTarget = $etcd->revision();
$etcd->put('/compaction/test', 'val2');
$etcd->put('/compaction/test', 'val3');
$etcd->compact($revCompactTarget);

$caughtCompacted = false;
try {
    $etcd->get('/compaction/test', $revBeforeCompact);
} catch (CompactedRevisionException $e) {
    $caughtCompacted = true;
}

$allowedRev = $etcd->get('/compaction/test', $revCompactTarget);
assertTest(
    'MVCC Compaction & CompactedRevisionException',
    $caughtCompacted && $allowedRev !== null && $allowedRev->value === 'val1',
    "Exception caught: " . ($caughtCompacted ? 'yes' : 'no')
);

// Test 7: Distributed Lease Grant & Keep-Alive
$lease = $etcd->leaseGrant(ttl: 30);
$ttlInitial = $etcd->leaseTimeToLive($lease->id);
$renewedTtl = $etcd->leaseKeepAlive($lease->id, ttl: 60);
$ttlAfterRenew = $etcd->leaseTimeToLive($lease->id);
assertTest(
    'Lease Grant & Keep-Alive Heartbeat',
    $lease->id > 0 &&
    $ttlInitial['remainingTtl'] <= 30 &&
    $renewedTtl >= 59 &&
    $ttlAfterRenew['remainingTtl'] >= 59,
    "Lease ID: {$lease->id}, Renewed: {$renewedTtl}"
);

// Test 8: Lease Cascade Key Eviction on Revoke
$leaseForKeys = $etcd->leaseGrant(ttl: 60);
$etcd->put('/leases/key-1', 'data-1', leaseId: $leaseForKeys->id);
$etcd->put('/leases/key-2', 'data-2', leaseId: $leaseForKeys->id);
$etcd->put('/leases/key-permanent', 'data-perm', leaseId: 0);

$leaseInfoBefore = $etcd->leaseTimeToLive($leaseForKeys->id);
$etcd->leaseRevoke($leaseForKeys->id);

$k1 = $etcd->get('/leases/key-1');
$k2 = $etcd->get('/leases/key-2');
$kPerm = $etcd->get('/leases/key-permanent');
assertTest(
    'Lease Cascade Key Eviction on Revocation',
    count($leaseInfoBefore['keys']) === 2 &&
    $k1 === null &&
    $k2 === null &&
    $kPerm !== null &&
    $kPerm->value === 'data-perm',
    "Keys attached: " . count($leaseInfoBefore['keys']) . ", k1: " . ($k1 ? 'exists' : 'null') . ", perm: " . ($kPerm ? 'exists' : 'null')
);

// Test 9: Lease Expiry Sweeper
$expiringLease = $etcd->leaseGrant(ttl: 1);
$etcd->put('/ephemeral/key', 'temp-val', leaseId: $expiringLease->id);
// Simulate future timestamp
$revokedIds = $etcd->checkExpiredLeases(now: time() + 5);
$expiredKey = $etcd->get('/ephemeral/key');
assertTest(
    'Lease Expiry Sweeper & Auto Eviction',
    in_array($expiringLease->id, $revokedIds, true) && $expiredKey === null,
    "Revoked count: " . count($revokedIds) . ", Ephemeral key: " . ($expiredKey ? 'exists' : 'null')
);

// Test 10: Real-Time Event Watcher Stream
$watchEvents = [];
$watcher = $etcd->watch('/stream/item', function (WatchEvent $event) use (&$watchEvents) {
    $watchEvents[] = $event;
});

$etcd->put('/stream/item', 'first');
$etcd->put('/stream/item', 'second');
$etcd->delete('/stream/item');

assertTest(
    'Real-Time Event Watcher Stream',
    count($watchEvents) === 3 &&
    $watchEvents[0]->type === EventType::PUT &&
    $watchEvents[0]->kv?->value === 'first' &&
    $watchEvents[1]->type === EventType::PUT &&
    $watchEvents[1]->kv?->value === 'second' &&
    $watchEvents[2]->type === EventType::DELETE,
    "Events received: " . count($watchEvents)
);

// Test 11: Prefix Event Watcher
$prefixEvents = [];
$prefixWatcher = $etcd->watch('/prefix/', function (WatchEvent $event) use (&$prefixEvents) {
    $prefixEvents[] = $event;
}, isPrefix: true);

$etcd->put('/prefix/sub1', 'alpha');
$etcd->put('/other/sub2', 'beta'); // Should NOT trigger prefix watcher
$etcd->put('/prefix/sub2', 'gamma');

assertTest(
    'Prefix Event Watcher Filtering',
    count($prefixEvents) === 2 &&
    $prefixEvents[0]->kv?->key === '/prefix/sub1' &&
    $prefixEvents[1]->kv?->key === '/prefix/sub2',
    "Prefix events received: " . count($prefixEvents)
);

// Test 12: Historical Revision Replay in Watcher
$replayEvents = [];
$startReplayRev = $watchEvents[0]->getRevision();
$replayWatcher = $etcd->watch(
    '/stream/item',
    function (WatchEvent $event) use (&$replayEvents) {
        $replayEvents[] = $event;
    },
    isPrefix: false,
    startRevision: $startReplayRev
);

assertTest(
    'Historical Revision Replay in Watcher',
    count($replayEvents) === 3 &&
    $replayEvents[0]->kv?->value === 'first' &&
    $replayEvents[1]->kv?->value === 'second' &&
    $replayEvents[2]->isDelete(),
    "Replayed events: " . count($replayEvents)
);

// Test 13: Atomic Transactions (Txn) - Successful If-Then
$etcd->put('/lock/leader', 'worker-1');
$txnResSuccess = $etcd->txn()
    ->If(CompareOp::value('/lock/leader', CompareResult::EQUAL, 'worker-1'))
    ->Then(
        TxnOp::put('/lock/leader', 'worker-2'),
        TxnOp::put('/lock/acquired_at', '2026-09-29T14:00:00Z')
    )
    ->Else(
        TxnOp::put('/lock/failed', 'true')
    )
    ->commit();

$leaderVal = $etcd->get('/lock/leader');
$failedVal = $etcd->get('/lock/failed');
assertTest(
    'Atomic Transactions (Txn) - Successful If-Then',
    $txnResSuccess->isSuccess() &&
    $leaderVal?->value === 'worker-2' &&
    $failedVal === null,
    "Txn succeeded: " . ($txnResSuccess->isSuccess() ? 'yes' : 'no') . ", Leader: " . ($leaderVal?->value ?? 'null')
);

// Test 14: Atomic Transactions (Txn) - Failed If-Else
$txnResFail = $etcd->txn()
    ->If(CompareOp::value('/lock/leader', CompareResult::EQUAL, 'wrong-worker'))
    ->Then(
        TxnOp::put('/lock/leader', 'worker-3')
    )
    ->Else(
        TxnOp::put('/lock/contention', 'detected')
    )
    ->commit();

$leaderAfterFail = $etcd->get('/lock/leader');
$contentionVal = $etcd->get('/lock/contention');
assertTest(
    'Atomic Transactions (Txn) - Failed If-Else Branch Execution',
    !$txnResFail->isSuccess() &&
    $leaderAfterFail?->value === 'worker-2' &&
    $contentionVal?->value === 'detected',
    "Txn succeeded: " . ($txnResFail->isSuccess() ? 'yes' : 'no') . ", Contention: " . ($contentionVal?->value ?? 'null')
);

// Test 15: Global Helpers & Dependency Injection
etcd_put('/global/helper/test', 'active-value');
$helperGet = etcd_get('/global/helper/test');
$helperWatcherFired = false;
$helperWatcher = etcd_watch('/global/helper/watched', function () use (&$helperWatcherFired) {
    $helperWatcherFired = true;
});
etcd_put('/global/helper/watched', 'triggered');

assertTest(
    'Global Helpers (etcd, etcd_put, etcd_get, etcd_watch)',
    $helperGet !== null &&
    $helperGet->value === 'active-value' &&
    $helperWatcherFired === true,
    "Helper get: " . ($helperGet?->value ?? 'null') . ", Watcher fired: " . ($helperWatcherFired ? 'yes' : 'no')
);

echo "\nSummary: {$passed} Passed, {$failed} Failed\n";
exit($failed === 0 ? 0 : 1);
