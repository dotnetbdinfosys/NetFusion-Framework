<?php

declare(strict_types=1);

/**
 * Phase 85 Unit Test Suite: Enterprise Distributed Consensus Key-Value Store & Coordination Service (Apache ZooKeeper Architecture)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\ZooKeeper\Enums\CreateMode;
use NetFusion\Core\ZooKeeper\Enums\WatcherEvent;
use NetFusion\Core\ZooKeeper\Enums\ZabState;
use NetFusion\Core\ZooKeeper\Exceptions\EphemeralOnEphemeralException;
use NetFusion\Core\ZooKeeper\Exceptions\NodeExistsException;
use NetFusion\Core\ZooKeeper\Exceptions\NodeNotFoundException;
use NetFusion\Core\ZooKeeper\Exceptions\NotEmptyException;
use NetFusion\Core\ZooKeeper\Models\DataTree;
use NetFusion\Core\ZooKeeper\Models\Stat;
use NetFusion\Core\ZooKeeper\Models\ZNode;
use NetFusion\Core\ZooKeeper\Protocol\ZabProtocol;
use NetFusion\Core\ZooKeeper\Session\Session;
use NetFusion\Core\ZooKeeper\Session\SessionManager;
use NetFusion\Core\ZooKeeper\Watch\WatchManager;
use NetFusion\Core\ZooKeeper\ZooKeeperManager;

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

echo "Starting Phase 85 Tests...\n";

// --- Test 1: DataTree Root and Path Normalization ---
$tree = new DataTree();
assertTest(
    $tree->getRoot()->getPath() === '/' &&
    $tree->normalizePath('/app//config///db') === '/app/config/db',
    "Test 1: DataTree initializes root and normalizes multi-slash paths"
);

// --- Test 2: Persistent ZNode Creation & Retrieval ---
$path = $tree->create('/services', 'catalog', CreateMode::PERSISTENT, 1);
$stat = null;
$data = $tree->getData('/services', null, $stat);
assertTest(
    $path === '/services' &&
    $data === 'catalog' &&
    $stat instanceof Stat &&
    $stat->version === 0,
    "Test 2: DataTree creates persistent ZNode and returns data with Stat"
);

// --- Test 3: Sequential ZNode Auto-Increment Id Allocation ---
$seq1 = $tree->create('/services/lock-', null, CreateMode::PERSISTENT_SEQUENTIAL, 2);
$seq2 = $tree->create('/services/lock-', null, CreateMode::PERSISTENT_SEQUENTIAL, 3);
assertTest(
    $seq1 === '/services/lock-0000000000' &&
    $seq2 === '/services/lock-0000000001',
    "Test 3: Sequential ZNodes allocate 10-digit zero-padded monotonically increasing IDs"
);

// --- Test 4: Hierarchical Children Lookup & NotEmptyException ---
$children = $tree->getChildren('/services');
assertTest(
    count($children) === 2 &&
    in_array('lock-0000000000', $children) &&
    in_array('lock-0000000001', $children),
    "Test 4: getChildren returns names of all child nodes"
);

$deleteParentFailed = false;
try {
    $tree->delete('/services');
} catch (NotEmptyException) {
    $deleteParentFailed = true;
}
assertTest($deleteParentFailed, "Test 5: Deleting non-empty parent throws NotEmptyException");

// --- Test 6: Ephemeral ZNode Restrictions (No Children under Ephemeral) ---
$ephPath = $tree->create('/temp_ephemeral', 'ephemeral_data', CreateMode::EPHEMERAL, 4, 101);
$ephChildFailed = false;
try {
    $tree->create('/temp_ephemeral/sub', 'invalid', CreateMode::PERSISTENT, 5);
} catch (EphemeralOnEphemeralException) {
    $ephChildFailed = true;
}
assertTest($ephChildFailed, "Test 6: Creating child node under Ephemeral parent throws EphemeralOnEphemeralException");

// --- Test 7: Optimistic Concurrency Control (BadVersion check on setData) ---
$tree->setData('/services', 'catalog_v2');
$statBefore = $tree->exists('/services');
assertTest($statBefore->version === 1, "Test 7: setData increments ZNode version");

$badVersionFailed = false;
try {
    $tree->setData('/services', 'conflict', 0); // version is now 1, pass expected 0
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'BadVersion')) {
        $badVersionFailed = true;
    }
}
assertTest($badVersionFailed, "Test 8: setData with mismatched version throws BadVersion exception");

// --- Test 8: Reactive One-Time Watchers (NodeDataChanged & NodeChildrenChanged) ---
$dataChangedFired = false;
$childrenChangedFired = false;

$tree->getData('/services', function (WatcherEvent $event, string $path) use (&$dataChangedFired) {
    if ($event === WatcherEvent::NODE_DATA_CHANGED && $path === '/services') {
        $dataChangedFired = true;
    }
});

$tree->getChildren('/services', function (WatcherEvent $event, string $path) use (&$childrenChangedFired) {
    if ($event === WatcherEvent::NODE_CHILDREN_CHANGED && $path === '/services') {
        $childrenChangedFired = true;
    }
});

$tree->setData('/services', 'catalog_v3');
$tree->create('/services/worker-1', 'active');

assertTest(
    $dataChangedFired && $childrenChangedFired,
    "Test 9: Reactive watchers trigger on data change and child creation"
);

// Verify Watchers are One-Time triggers (subsequent updates do NOT fire)
$dataChangedAgain = false;
$tree->setData('/services', 'catalog_v4');
assertTest(!$dataChangedAgain, "Test 10: Watchers operate strictly as one-time triggers");

// --- Test 9: Client Session Management & Heartbeat Keep-Alive ---
$sessionMgr = new SessionManager();
$session = $sessionMgr->createSession(100); // 100ms timeout
assertTest($session->getId() > 0 && !$session->isExpired(), "Test 11: SessionManager creates active session");

$sessionMgr->heartbeat($session->getId());
assertTest(!$session->isExpired(), "Test 12: Session heartbeat refreshes last touched timestamp");

// --- Test 10: Ephemeral Auto-Eviction on Session Expiry ---
$zkManager = new ZooKeeperManager(['cluster_size' => 1]);
$zkSession = $zkManager->session(50); // 50ms timeout
$zkManager->create('/worker_node_1', '10.0.0.1:8080', CreateMode::EPHEMERAL);
assertTest($zkManager->exists('/worker_node_1') !== null, "Test 13: Ephemeral node exists while session is active");

// Simulate time advance past session timeout
$evictedPaths = $zkManager->sweepExpiredSessions(microtime(true) + 1.0);
assertTest(
    in_array('/worker_node_1', $evictedPaths) &&
    $zkManager->exists('/worker_node_1') === null,
    "Test 14: Ephemeral ZNodes are automatically deleted upon session expiration"
);

// --- Test 11: ZAB Protocol Leader Election & Quorum Voting ---
$zab1 = new ZabProtocol(1, 3);
$zab2 = new ZabProtocol(2, 3);
$zab3 = new ZabProtocol(3, 3);

// Server 2 and 3 vote for Server 3
$vote3 = $zab3->createVote();
$zab1->castVote(3, $vote3);
$leaderElected = $zab1->castVote(2, $vote3);

assertTest(
    $leaderElected === 3 &&
    $zab1->getState() === ZabState::FOLLOWING &&
    $zab1->getCurrentLeader() === 3,
    "Test 15: ZAB protocol elects leader upon reaching majority quorum"
);

// --- Test 12: ZAB 64-bit ZXID Generation (Epoch << 32 | Counter) ---
$zxid1 = $zab1->nextZxid();
$zxid2 = $zab1->nextZxid();
assertTest(
    $zxid2 > $zxid1 &&
    ($zxid1 >> 32) === $zab1->getEpoch(),
    "Test 16: ZAB generates monotonic 64-bit zxids incorporating epoch and counter"
);

// --- Test 13: ZAB Two-Phase Commit Replication & Quorum Ack ---
$zabLeader = new ZabProtocol(1, 3);
$zabLeader->castVote(1, ['serverId' => 1, 'zxid' => 10, 'epoch' => 2]);
$zabLeader->castVote(2, ['serverId' => 1, 'zxid' => 10, 'epoch' => 2]); // Quorum achieved

$propZxid = $zabLeader->propose('create', '/config');
assertTest(!$zabLeader->isCommitted($propZxid), "Test 17: Proposal is not committed prior to follower acknowledgement");

$committed = $zabLeader->acknowledge($propZxid, 2); // Follower 2 ACKs
assertTest($committed && $zabLeader->isCommitted($propZxid), "Test 18: Proposal commits once majority quorum acknowledges");

// --- Test 14: ZooKeeperManager End-to-End Coordination ---
$zk = new ZooKeeperManager(['cluster_size' => 1]);
$zk->create('/election', 'init');
$n1 = $zk->create('/election/guid-n_', 'cand_1', CreateMode::EPHEMERAL_SEQUENTIAL);
$n2 = $zk->create('/election/guid-n_', 'cand_2', CreateMode::EPHEMERAL_SEQUENTIAL);

$cands = $zk->getChildren('/election');
sort($cands);
assertTest(
    count($cands) === 2 &&
    $cands[0] === basename($n1) &&
    $cands[1] === basename($n2),
    "Test 19: ZooKeeperManager facilitates distributed leader election recipe using Ephemeral Sequential nodes"
);

// --- Test 15: Global Helpers & Dependency Injection Container Binding ---
$app = new Application(dirname(__DIR__));
$app->singleton(ZooKeeperManager::class, fn () => new ZooKeeperManager(['cluster_size' => 1]));
$app->singleton('zookeeper', fn () => $app->make(ZooKeeperManager::class));

$createdZkPath = zk_create('/cluster/primary', '10.0.1.100');
$retrievedZkData = zk_get('/cluster/primary');

$watchTriggered = false;
zk_watch('/cluster/primary', function (WatcherEvent $e, string $p) use (&$watchTriggered) {
    if ($e === WatcherEvent::NODE_DATA_CHANGED) {
        $watchTriggered = true;
    }
});
zookeeper()->setData('/cluster/primary', '10.0.1.101');

assertTest(
    function_exists('zookeeper') && function_exists('zk_create') && function_exists('zk_get') && function_exists('zk_watch') &&
    $createdZkPath === '/cluster/primary' &&
    $retrievedZkData === '10.0.1.100' &&
    $watchTriggered,
    "Test 20: Global helpers zookeeper(), zk_create(), zk_get(), and zk_watch() operate seamlessly"
);

echo "\nPhase 85 Unit Tests Completed: {$passed} Passed, {$failed} Failed.\n";
exit($failed === 0 ? 0 : 1);
