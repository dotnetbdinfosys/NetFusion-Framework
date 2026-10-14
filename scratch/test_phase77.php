<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Phase 77 Test Suite
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\DistributedDb\DistributedDbManager;
use NetFusion\Core\DistributedDb\DistributedSql\DistributedQueryParser;
use NetFusion\Core\DistributedDb\DistributedSql\DistributedQueryPlanner;
use NetFusion\Core\DistributedDb\DistributedSql\PartitionStrategy;
use NetFusion\Core\DistributedDb\DistributedSql\QueryPlan;
use NetFusion\Core\DistributedDb\DistributedSql\ScatterGatherExecutor;
use NetFusion\Core\DistributedDb\DistributedSql\ShardDescriptor;
use NetFusion\Core\DistributedDb\DistributedSql\SqlNodeType;
use NetFusion\Core\DistributedDb\DistributedSql\TablePartitionConfig;
use NetFusion\Core\DistributedDb\MultiMaster\ConflictResolutionStrategy;
use NetFusion\Core\DistributedDb\MultiMaster\ConflictResolver;
use NetFusion\Core\DistributedDb\MultiMaster\GeoRegionNode;
use NetFusion\Core\DistributedDb\MultiMaster\MultiMasterCoordinator;
use NetFusion\Core\DistributedDb\MultiMaster\ReplicationEvent;
use NetFusion\Core\DistributedDb\MultiMaster\ReplicationMode;
use NetFusion\Core\DistributedDb\Raft\AppendEntriesResponse;
use NetFusion\Core\DistributedDb\Raft\AppendEntriesRpc;
use NetFusion\Core\DistributedDb\Raft\LogEntry;
use NetFusion\Core\DistributedDb\Raft\RaftCluster;
use NetFusion\Core\DistributedDb\Raft\RaftNode;
use NetFusion\Core\DistributedDb\Raft\RaftRole;
use NetFusion\Core\DistributedDb\Raft\RequestVoteResponse;
use NetFusion\Core\DistributedDb\Raft\RequestVoteRpc;
use NetFusion\Core\DistributedDb\TwoPhaseCommit\DistributedLock;
use NetFusion\Core\DistributedDb\TwoPhaseCommit\DistributedLockManager;
use NetFusion\Core\DistributedDb\TwoPhaseCommit\ParticipantVote;
use NetFusion\Core\DistributedDb\TwoPhaseCommit\TransactionState;
use NetFusion\Core\DistributedDb\TwoPhaseCommit\TwoPhaseCoordinator;
use NetFusion\Core\DistributedDb\TwoPhaseCommit\TwoPhaseParticipant;

// Bootstrap application container
$app = new Application(dirname(__DIR__));
$app->singleton(DistributedDbManager::class, fn() => new DistributedDbManager());

$passed = 0;
$failed = 0;

function it(string $description, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  ✔ [P77] {$description}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ✘ [P77] {$description}\n";
        echo "     Error: {$e->getMessage()}\n";
        echo "     At: {$e->getFile()}:{$e->getLine()}\n";
        $failed++;
    }
}

echo "=== NetFusion Phase 77: Geo-Distributed Multi-Master, Raft & Distributed SQL Suite ===\n\n";

// ==========================================
// 1. Raft Consensus Primitives & RPC Models
// ==========================================

it('LogEntry calculates deterministic SHA-256 fingerprint', function () {
    $entry1 = new LogEntry(term: 1, index: 1, command: 'SET:key1', payload: 'val1', clientRequestId: 'req-1');
    $entry2 = new LogEntry(term: 1, index: 1, command: 'SET:key1', payload: 'val1', clientRequestId: 'req-1');
    $entry3 = new LogEntry(term: 1, index: 2, command: 'SET:key2', payload: 'val2', clientRequestId: 'req-2');

    assert($entry1->hash() === $entry2->hash());
    assert($entry1->hash() !== $entry3->hash());
    assert(strlen($entry1->hash()) === 64);
});

it('AppendEntriesRpc detects heartbeat when entries are empty', function () {
    $heartbeat = new AppendEntriesRpc(term: 2, leaderId: 'node-1', prevLogIndex: 3, prevLogTerm: 2, entries: []);
    $replication = new AppendEntriesRpc(
        term: 2,
        leaderId: 'node-1',
        prevLogIndex: 3,
        prevLogTerm: 2,
        entries: [new LogEntry(term: 2, index: 4, command: 'SET:x', payload: 10)]
    );

    assert($heartbeat->isHeartbeat() === true);
    assert($replication->isHeartbeat() === false);
});

it('RequestVoteRpc serializes and deserializes accurately', function () {
    $rpc = new RequestVoteRpc(term: 3, candidateId: 'node-2', lastLogIndex: 5, lastLogTerm: 2);
    $array = $rpc->toArray();
    $restored = RequestVoteRpc::fromArray($array);

    assert($restored->term === 3);
    assert($restored->candidateId === 'node-2');
    assert($restored->lastLogIndex === 5);
    assert($restored->lastLogTerm === 2);
});

// ==========================================
// 2. Raft Node State Transitions & Handlers
// ==========================================

it('RaftNode starts in FOLLOWER role at term 0', function () {
    $node = new RaftNode('node-1');
    assert($node->getNodeId() === 'node-1');
    assert($node->getRole() === RaftRole::FOLLOWER);
    assert($node->getCurrentTerm() === 0);
    assert($node->getCommitIndex() === 0);
    assert($node->getLastLogIndex() === 0);
});

it('RaftNode transitions to CANDIDATE and increments term on becomeCandidate()', function () {
    $node = new RaftNode('node-1');
    $voteRpc = $node->becomeCandidate();

    assert($node->getRole() === RaftRole::CANDIDATE);
    assert($node->getCurrentTerm() === 1);
    assert($node->getVotedFor() === 'node-1');
    assert($voteRpc->candidateId === 'node-1');
    assert($voteRpc->term === 1);
});

it('RaftNode denies vote if candidate term is lower than current term', function () {
    $node = new RaftNode('node-1');
    $node->becomeCandidate(); // term = 1

    $staleRpc = new RequestVoteRpc(term: 0, candidateId: 'node-2', lastLogIndex: 0, lastLogTerm: 0);
    $response = $node->handleRequestVote($staleRpc);

    assert($response->voteGranted === false);
    assert($response->term === 1);
});

it('RaftNode grants vote to valid candidate and denies second candidate in same term', function () {
    $node = new RaftNode('node-1');
    $rpc1 = new RequestVoteRpc(term: 1, candidateId: 'node-2', lastLogIndex: 0, lastLogTerm: 0);
    $response1 = $node->handleRequestVote($rpc1);

    assert($response1->voteGranted === true);
    assert($node->getVotedFor() === 'node-2');

    // Second candidate in term 1
    $rpc2 = new RequestVoteRpc(term: 1, candidateId: 'node-3', lastLogIndex: 0, lastLogTerm: 0);
    $response2 = $node->handleRequestVote($rpc2);

    assert($response2->voteGranted === false);
});

it('RaftNode denies vote if candidate log is less up-to-date than receiver', function () {
    $node = new RaftNode('node-1');
    $node->becomeCandidate();
    $node->becomeLeader(['node-2']);
    $node->appendCommand('SET:key', 'val'); // term 1, index 1

    // Candidate has lower lastLogIndex
    $candidateRpc = new RequestVoteRpc(term: 2, candidateId: 'node-2', lastLogIndex: 0, lastLogTerm: 0);
    $response = $node->handleRequestVote($candidateRpc);

    assert($response->voteGranted === false);
});

it('RaftNode rejects AppendEntries if log consistency check fails', function () {
    $node = new RaftNode('node-1'); // empty log

    // Leader assumes follower has prevLogIndex=2
    $rpc = new AppendEntriesRpc(
        term: 1,
        leaderId: 'leader-1',
        prevLogIndex: 2,
        prevLogTerm: 1,
        entries: [new LogEntry(term: 1, index: 3, command: 'SET:a', payload: 1)]
    );

    $response = $node->handleAppendEntries($rpc);
    assert($response->success === false);
});

it('RaftNode appends valid log entries and applies committed entries to state machine', function () {
    $node = new RaftNode('follower-1');

    $rpc = new AppendEntriesRpc(
        term: 1,
        leaderId: 'leader-1',
        prevLogIndex: 0,
        prevLogTerm: 0,
        entries: [
            new LogEntry(term: 1, index: 1, command: 'SET:site_name', payload: 'NetFusion'),
            new LogEntry(term: 1, index: 2, command: 'SET:version', payload: '1.0.0'),
        ],
        leaderCommit: 2
    );

    $response = $node->handleAppendEntries($rpc);

    assert($response->success === true);
    assert($node->getCommitIndex() === 2);
    assert($node->getStateValue('site_name') === 'NetFusion');
    assert($node->getStateValue('version') === '1.0.0');
});

// ==========================================
// 3. Raft Cluster & Quorum Leader Election
// ==========================================

it('RaftCluster calculates majority quorum correctly', function () {
    $cluster3 = new RaftCluster([new RaftNode('n1'), new RaftNode('n2'), new RaftNode('n3')]);
    $cluster5 = new RaftCluster([new RaftNode('n1'), new RaftNode('n2'), new RaftNode('n3'), new RaftNode('n4'), new RaftNode('n5')]);

    assert($cluster3->getQuorum() === 2); // floor(3/2) + 1 = 2
    assert($cluster5->getQuorum() === 3); // floor(5/2) + 1 = 3
});

it('RaftCluster elects leader with majority quorum', function () {
    $cluster = new RaftCluster([
        new RaftNode('n1'),
        new RaftNode('n2'),
        new RaftNode('n3'),
    ]);

    $leader = $cluster->electLeader('n1');
    assert($leader !== null);
    assert($leader->getNodeId() === 'n1');
    assert($leader->getRole() === RaftRole::LEADER);
    assert($cluster->getLeader()?->getNodeId() === 'n1');
});

it('RaftCluster replicates command to followers and commits via propose()', function () {
    $cluster = new RaftCluster([
        new RaftNode('n1'),
        new RaftNode('n2'),
        new RaftNode('n3'),
    ]);

    $cluster->electLeader('n1');
    $entry = $cluster->propose('SET:cluster_mode', 'distributed');

    assert($entry->index === 1);
    assert($entry->command === 'SET:cluster_mode');

    // Followers should have received and committed the entry
    $n2 = $cluster->getNode('n2');
    $n3 = $cluster->getNode('n3');

    assert($n2->getCommitIndex() === 1);
    assert($n3->getCommitIndex() === 1);
    assert($n2->getStateValue('cluster_mode') === 'distributed');
    assert($n3->getStateValue('cluster_mode') === 'distributed');
});

it('RaftCluster handles network partitions preventing split-brain', function () {
    $cluster = new RaftCluster([
        new RaftNode('n1'),
        new RaftNode('n2'),
        new RaftNode('n3'),
        new RaftNode('n4'),
        new RaftNode('n5'),
    ]);

    // Partition minority (n4, n5) away from majority (n1, n2, n3)
    $cluster->partition(['n4', 'n5'], ['n1', 'n2', 'n3']);

    // Attempt to elect leader in minority partition
    $minorityLeader = $cluster->electLeader('n4');
    assert($minorityLeader === null); // Cannot achieve quorum of 3

    // Elect leader in majority partition
    $majorityLeader = $cluster->electLeader('n1');
    assert($majorityLeader !== null);
    assert($majorityLeader->getNodeId() === 'n1');

    // Heal partition
    $cluster->healNetwork();
    $cluster->broadcastHeartbeat($majorityLeader);
});

// ==========================================
// 4. Geo Multi-Master Replication & Conflicts
// ==========================================

it('ConflictResolver applies Last-Write-Wins (LWW) with deterministic region tie-breaker', function () {
    $resolver = new ConflictResolver();

    $evt1 = new ReplicationEvent(
        eventId: 'e1',
        table: 'users',
        primaryKey: 101,
        operation: 'UPDATE',
        oldData: [],
        newData: ['name' => 'Alice Local'],
        originRegion: 'us-east-1',
        timestamp: 1000
    );

    $evt2 = new ReplicationEvent(
        eventId: 'e2',
        table: 'users',
        primaryKey: 101,
        operation: 'UPDATE',
        oldData: [],
        newData: ['name' => 'Alice Remote'],
        originRegion: 'eu-west-1',
        timestamp: 2000
    );

    $winner = $resolver->resolve($evt1, $evt2, ConflictResolutionStrategy::LAST_WRITE_WINS);
    assert($winner->newData['name'] === 'Alice Remote');

    // Tie test with equal timestamps
    $evtTieLocal = new ReplicationEvent(
        eventId: 'e3',
        table: 'users',
        primaryKey: 101,
        operation: 'UPDATE',
        oldData: [],
        newData: ['name' => 'Tie A'],
        originRegion: 'ap-south-1',
        timestamp: 3000
    );
    $evtTieRemote = new ReplicationEvent(
        eventId: 'e4',
        table: 'users',
        primaryKey: 101,
        operation: 'UPDATE',
        oldData: [],
        newData: ['name' => 'Tie B'],
        originRegion: 'us-east-1',
        timestamp: 3000
    );

    $tieWinner = $resolver->resolve($evtTieLocal, $evtTieRemote, ConflictResolutionStrategy::LAST_WRITE_WINS);
    // 'us-east-1' > 'ap-south-1' lexicographically
    assert($tieWinner->originRegion === 'us-east-1');
});

it('ConflictResolver detects vector clock causal dominance', function () {
    $resolver = new ConflictResolver();

    $evtLocal = new ReplicationEvent(
        eventId: 'e1',
        table: 'posts',
        primaryKey: 1,
        operation: 'UPDATE',
        oldData: [],
        newData: ['title' => 'V1'],
        originRegion: 'us-east-1',
        vectorClock: ['us-east-1' => 1, 'eu-west-1' => 0]
    );

    $evtRemote = new ReplicationEvent(
        eventId: 'e2',
        table: 'posts',
        primaryKey: 1,
        operation: 'UPDATE',
        oldData: [],
        newData: ['title' => 'V2'],
        originRegion: 'eu-west-1',
        vectorClock: ['us-east-1' => 1, 'eu-west-1' => 1] // Strictly dominates
    );

    $winner = $resolver->resolve($evtLocal, $evtRemote, ConflictResolutionStrategy::VECTOR_CLOCK);
    assert($winner->newData['title'] === 'V2');
});

it('ConflictResolver performs CRDT column-level merge of disjoint fields', function () {
    $resolver = new ConflictResolver();

    $evtA = new ReplicationEvent(
        eventId: 'eA',
        table: 'accounts',
        primaryKey: 50,
        operation: 'UPDATE',
        oldData: [],
        newData: ['email' => 'user@example.com', 'tier' => 'gold'],
        originRegion: 'us-east-1'
    );

    $evtB = new ReplicationEvent(
        eventId: 'eB',
        table: 'accounts',
        primaryKey: 50,
        operation: 'UPDATE',
        oldData: [],
        newData: ['phone' => '+1555123456', 'tier' => 'platinum'],
        originRegion: 'eu-west-1'
    );

    $merged = $resolver->resolve($evtA, $evtB, ConflictResolutionStrategy::CRDT_MERGE);

    assert(isset($merged->newData['email']));
    assert(isset($merged->newData['phone']));
    assert($merged->newData['email'] === 'user@example.com');
    assert($merged->newData['phone'] === '+1555123456');
    assert($merged->newData['tier'] === 'platinum');
});

it('MultiMasterCoordinator synchronizes mutations across geo-regions', function () {
    $us = new GeoRegionNode('us-east-1');
    $eu = new GeoRegionNode('eu-west-1');
    $ap = new GeoRegionNode('ap-southeast-1');

    $coordinator = new MultiMasterCoordinator([$us, $eu, $ap], ReplicationMode::ASYNC);

    // Write on US region
    $coordinator->write('us-east-1', 'products', 'sku-100', ['name' => 'Laptop', 'price' => 1200]);

    // Outboxes of EU and AP should be populated
    assert($us->getPendingOutboxCount() === 2);
    assert($eu->getRow('products', 'sku-100') === null);

    // Trigger synchronization
    $synced = $coordinator->synchronize();

    assert($synced === 2);
    assert($us->getPendingOutboxCount() === 0);
    assert($eu->getRow('products', 'sku-100')['name'] === 'Laptop');
    assert($ap->getRow('products', 'sku-100')['price'] === 1200);
});

// ==========================================
// 5. Distributed SQL Parser, Planner & Engine
// ==========================================

it('DistributedQueryParser extracts SELECT predicates and aggregate functions', function () {
    $parser = new DistributedQueryParser();
    $sql = "SELECT department, COUNT(*) AS emp_count, AVG(salary) AS avg_sal FROM employees WHERE status = 'active' ORDER BY emp_count DESC LIMIT 10 OFFSET 5";

    $parsed = $parser->parse($sql);

    assert($parsed['type'] === SqlNodeType::SELECT);
    assert($parsed['table'] === 'employees');
    assert(isset($parsed['aggregates']['emp_count']));
    assert($parsed['aggregates']['emp_count']['func'] === 'COUNT');
    assert(isset($parsed['aggregates']['avg_sal']));
    assert($parsed['aggregates']['avg_sal']['func'] === 'AVG');
    assert($parsed['order_by'] === 'emp_count');
    assert($parsed['order_dir'] === 'DESC');
    assert($parsed['limit'] === 10);
    assert($parsed['offset'] === 5);
    assert(count($parsed['where']) === 1);
    assert($parsed['where'][0]['column'] === 'status');
});

it('DistributedQueryParser extracts partition key from INSERT VALUES', function () {
    $parser = new DistributedQueryParser();
    $sql = "INSERT INTO orders (order_id, customer_id, amount) VALUES (9001, 42, 99.50)";

    $parsed = $parser->parse($sql, [], 'customer_id');

    assert($parsed['type'] === SqlNodeType::INSERT);
    assert($parsed['table'] === 'orders');
    assert($parsed['partition_key_value'] === 42);
    assert($parsed['insert_data']['order_id'] === 9001);
});

it('ShardDescriptor matches HASH and RANGE partition rules', function () {
    $hashShard = new ShardDescriptor('shard_0', 'node-1', PartitionStrategy::HASH, hashModulus: 4, hashRemainder: 0);
    $rangeShard = new ShardDescriptor('shard_eur', 'node-2', PartitionStrategy::RANGE, rangeStart: 1000, rangeEnd: 2000);

    // HASH match: 8 % 4 == 0; 9 % 4 == 1
    assert($hashShard->matches(8) === true);
    assert($hashShard->matches(9) === false);

    // RANGE match: 1500 is in [1000, 2000)
    assert($rangeShard->matches(1500) === true);
    assert($rangeShard->matches(2500) === false);
});

it('DistributedQueryPlanner creates point-lookup plan when partition key is supplied', function () {
    $planner = new DistributedQueryPlanner();
    $config = new TablePartitionConfig('users', 'user_id', PartitionStrategy::HASH, [
        new ShardDescriptor('shard_0', 'n1', PartitionStrategy::HASH, hashModulus: 2, hashRemainder: 0),
        new ShardDescriptor('shard_1', 'n2', PartitionStrategy::HASH, hashModulus: 2, hashRemainder: 1),
    ]);
    $planner->addTableConfig($config);

    // Point lookup query: user_id = 4 (4 % 2 == 0 -> shard_0)
    $plan = $planner->plan("SELECT * FROM users WHERE user_id = 4");

    assert($plan->isPointLookup() === true);
    assert($plan->isScatterGather === false);
    assert($plan->getTargetShardIds() === ['shard_0']);

    // Scatter gather query: without partition key
    $scatterPlan = $planner->plan("SELECT * FROM users WHERE status = 'active'");
    assert($scatterPlan->isScatterGather === true);
    assert(count($scatterPlan->getTargetShardIds()) === 2);
});

it('ScatterGatherExecutor resolves distributed query and merges global aggregates', function () {
    $executor = new ScatterGatherExecutor();

    // Shard 0 rows
    $executor->setShardTableData('shard_0', 'sales', [
        ['id' => 1, 'region_id' => 0, 'amount' => 100],
        ['id' => 2, 'region_id' => 0, 'amount' => 200],
    ]);

    // Shard 1 rows
    $executor->setShardTableData('shard_1', 'sales', [
        ['id' => 3, 'region_id' => 1, 'amount' => 300],
        ['id' => 4, 'region_id' => 1, 'amount' => 400],
    ]);

    $planner = new DistributedQueryPlanner([
        new TablePartitionConfig('sales', 'region_id', PartitionStrategy::HASH, [
            new ShardDescriptor('shard_0', 'n1', PartitionStrategy::HASH, hashModulus: 2, hashRemainder: 0),
            new ShardDescriptor('shard_1', 'n2', PartitionStrategy::HASH, hashModulus: 2, hashRemainder: 1),
        ])
    ]);

    // 1. Scatter-gather SELECT with SUM, COUNT, AVG, MIN, MAX
    $plan = $planner->plan("SELECT COUNT(*) AS total_sales, SUM(amount) AS sum_amount, AVG(amount) AS avg_amount, MIN(amount) AS min_amount, MAX(amount) AS max_amount FROM sales");
    $result = $executor->execute($plan);

    assert(count($result) === 1);
    assert($result[0]['total_sales'] === 4);
    assert((float) $result[0]['sum_amount'] === 1000.0);
    assert((float) $result[0]['avg_amount'] === 250.0); // 1000 / 4 = 250
    assert((float) $result[0]['min_amount'] === 100.0);
    assert((float) $result[0]['max_amount'] === 400.0);

    // 2. Global ORDER BY and LIMIT across shards
    $orderPlan = $planner->plan("SELECT * FROM sales ORDER BY amount DESC LIMIT 2");
    $orderResult = $executor->execute($orderPlan);

    assert(count($orderResult) === 2);
    assert($orderResult[0]['amount'] === 400);
    assert($orderResult[1]['amount'] === 300);
});

// ==========================================
// 6. Two-Phase Commit (2PC) & Distributed Lock
// ==========================================

it('TwoPhaseCoordinator commits atomic transaction when all participants vote commit', function () {
    $shardA = new TwoPhaseParticipant('shard-A');
    $shardB = new TwoPhaseParticipant('shard-B');

    $coordinator = new TwoPhaseCoordinator([$shardA, $shardB]);

    $result = $coordinator->executeAtomic([
        'shard-A' => ['account_1' => -500],
        'shard-B' => ['account_2' => 500],
    ]);

    assert($result['success'] === true);
    assert($result['state'] === TransactionState::COMMITTED);
    assert($shardA->getTransactionState($result['tx_id']) === TransactionState::COMMITTED);
    assert($shardB->getTransactionState($result['tx_id']) === TransactionState::COMMITTED);
    assert($shardA->getCommittedData()['account_1'] === -500);
    assert($shardB->getCommittedData()['account_2'] === 500);
});

it('TwoPhaseCoordinator aborts transaction and rolls back when any participant fails prepare', function () {
    $shardA = new TwoPhaseParticipant('shard-A');
    $shardB = (new TwoPhaseParticipant('shard-B'))->setForceAbort(true); // Simulates failure

    $coordinator = new TwoPhaseCoordinator([$shardA, $shardB]);

    $result = $coordinator->executeAtomic([
        'shard-A' => ['account_1' => -500],
        'shard-B' => ['account_2' => 500],
    ]);

    assert($result['success'] === false);
    assert($result['state'] === TransactionState::ABORTED);
    assert($shardA->getTransactionState($result['tx_id']) === TransactionState::ABORTED);
    assert(empty($shardA->getCommittedData()));
});

it('DistributedLockManager acquires lease lock with monotonic fencing token and blocks contender', function () {
    $lockManager = new DistributedLockManager();

    $lock1 = $lockManager->acquire('order:1001', 'worker-A', ttlMs: 1000);
    assert($lock1 !== null);
    assert($lock1->resource === 'order:1001');
    assert($lock1->owner === 'worker-A');
    assert($lock1->fencingToken === 1);
    assert($lock1->isExpired() === false);

    // Contender cannot acquire active lock
    $lockContender = $lockManager->acquire('order:1001', 'worker-B', ttlMs: 1000);
    assert($lockContender === null);

    // Releasing lock allows next acquisition with incremented fencing token
    $released = $lockManager->release($lock1);
    assert($released === true);

    $lock2 = $lockManager->acquire('order:1001', 'worker-B', ttlMs: 1000);
    assert($lock2 !== null);
    assert($lock2->owner === 'worker-B');
    assert($lock2->fencingToken === 2);
});

it('DistributedLockManager withLock() executes callback and guarantees release', function () {
    $lockManager = new DistributedLockManager();

    $res = $lockManager->withLock('inventory:item_99', 'worker-1', function (DistributedLock $lock) {
        assert($lock->resource === 'inventory:item_99');
        return 'processed_with_lock';
    });

    assert($res === 'processed_with_lock');
    assert(empty($lockManager->getActiveLocks())); // Released
});

// ==========================================
// 7. Facade & Global Helpers
// ==========================================

it('DistributedDbManager provides unified access to all distributed engines', function () {
    $manager = new DistributedDbManager();

    assert($manager->raft() instanceof RaftCluster);
    assert($manager->multiMaster() instanceof MultiMasterCoordinator);
    assert($manager->planner() instanceof DistributedQueryPlanner);
    assert($manager->executor() instanceof ScatterGatherExecutor);
    assert($manager->twoPhase() instanceof TwoPhaseCoordinator);
    assert($manager->lock() instanceof DistributedLockManager);
});

it('Global helpers distributed_db(), raft_cluster(), dsql(), and dist_lock() operate seamlessly', function () {
    assert(distributed_db() instanceof DistributedDbManager);
    assert(raft_cluster() instanceof RaftCluster);

    // dist_lock() helper
    $output = dist_lock('test:resource', function ($lock) {
        return 'locked_' . $lock->fencingToken;
    });

    assert(str_starts_with($output, 'locked_'));
});

it('ConflictResolver executes user-defined custom callable resolver', function () {
    $resolver = new ConflictResolver();
    $evt1 = new ReplicationEvent('e1', 'items', 1, 'UPDATE', [], ['stock' => 10], 'us-east');
    $evt2 = new ReplicationEvent('e2', 'items', 1, 'UPDATE', [], ['stock' => 5], 'eu-west');

    // Custom resolver sums stocks
    $winner = $resolver->resolve(
        $evt1,
        $evt2,
        ConflictResolutionStrategy::CUSTOM_CALLABLE,
        function (ReplicationEvent $local, ReplicationEvent $remote) {
            return new ReplicationEvent(
                'e_custom',
                $local->table,
                $local->primaryKey,
                'UPDATE',
                [],
                ['stock' => $local->newData['stock'] + $remote->newData['stock']],
                'custom'
            );
        }
    );

    assert($winner->newData['stock'] === 15);
});

it('MultiMasterCoordinator replicates synchronously in SYNC_QUORUM mode', function () {
    $r1 = new GeoRegionNode('us-1');
    $r2 = new GeoRegionNode('eu-1');
    $r3 = new GeoRegionNode('ap-1');

    $coord = new MultiMasterCoordinator([$r1, $r2, $r3], ReplicationMode::SYNC_QUORUM);
    $coord->write('us-1', 'configs', 'mode', ['val' => 'prod']);

    // Since SYNC_QUORUM requires majority (2 out of 3), at least 1 peer is immediately updated
    $hasUpdate = ($r2->getRow('configs', 'mode') !== null) || ($r3->getRow('configs', 'mode') !== null);
    assert($hasUpdate === true);
});

it('DistributedQueryParser parses UPDATE and DELETE statements', function () {
    $parser = new DistributedQueryParser();

    $updateSql = "UPDATE accounts SET balance = 500, status = 'active' WHERE id = 10";
    $parsedUp = $parser->parse($updateSql);
    assert($parsedUp['type'] === SqlNodeType::UPDATE);
    assert($parsedUp['table'] === 'accounts');
    assert($parsedUp['update_data']['balance'] === 500);

    $deleteSql = "DELETE FROM sessions WHERE expired = 1";
    $parsedDel = $parser->parse($deleteSql);
    assert($parsedDel['type'] === SqlNodeType::DELETE);
    assert($parsedDel['table'] === 'sessions');
});

it('DistributedLockManager renew() extends active lease expiration', function () {
    $lm = new DistributedLockManager();
    $lock = $lm->acquire('db:tx', 'proc-1', ttlMs: 1000);
    assert($lock !== null);

    $initialExpires = $lock->expiresAt;
    usleep(10000); // 10ms
    $renewed = $lm->renew($lock, ttlMs: 2000);

    assert($renewed === true);
    $active = $lm->getActiveLocks();
    assert($active['db:tx']->expiresAt > $initialExpires);
});

it('ScatterGatherExecutor performs partitioned INSERT and UPDATE', function () {
    $planner = new DistributedQueryPlanner([
        new TablePartitionConfig('users', 'user_id', PartitionStrategy::HASH, [
            new ShardDescriptor('shard_0', 'n1', PartitionStrategy::HASH, hashModulus: 2, hashRemainder: 0),
            new ShardDescriptor('shard_1', 'n2', PartitionStrategy::HASH, hashModulus: 2, hashRemainder: 1),
        ])
    ]);
    $executor = new ScatterGatherExecutor();

    // Insert user_id 4 (routes to shard_0)
    $planInsert = $planner->plan("INSERT INTO users (user_id, name) VALUES (4, 'Alice')");
    assert($planInsert->getTargetShardIds() === ['shard_0']);
    $resInsert = $executor->execute($planInsert);
    assert($resInsert['affected_rows'] === 1);

    // Update user_id 4
    $planUpdate = $planner->plan("UPDATE users SET name = 'Alice Cooper' WHERE user_id = 4");
    $resUpdate = $executor->execute($planUpdate);
    assert($resUpdate['affected_rows'] === 1);
});

it('Console commands raft:status, db:replicate, and dsql:query execute cleanly', function () {
    global $app;
    $statusCmd = new \NetFusion\Core\Console\Commands\RaftStatusCommand($app);
    assert($statusCmd->getName() === 'raft:status');

    $replCmd = new \NetFusion\Core\Console\Commands\DbReplicateCommand($app);
    assert($replCmd->getName() === 'db:replicate');

    $dsqlCmd = new \NetFusion\Core\Console\Commands\DsqlQueryCommand($app);
    assert($dsqlCmd->getName() === 'dsql:query');
});

it('Database migration up and down executes successfully for distributed db schema', function () {
    $migration = require dirname(__DIR__) . '/database/migrations/2026_09_28_000060_create_distributed_db_tables.php';

    $conn = new \NetFusion\Core\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);

    $migration->up($conn);

    // Check table creation
    $pdo = $conn->getPdo();
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='distributed_raft_logs'");
    $hasTable = ($stmt->fetch() !== false);
    $stmt->closeCursor();
    unset($stmt);
    assert($hasTable === true);

    $migration->down($conn);
});

// ==========================================
// Summary
// ==========================================
echo "\n--------------------------------------------------\n";
echo "Total Tests : " . ($passed + $failed) . "\n";
echo "Passed      : {$passed}\n";
echo "Failed      : {$failed}\n";
echo "Pass Rate   : " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n";
echo "--------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
