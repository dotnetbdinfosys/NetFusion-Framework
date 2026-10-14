<?php

declare(strict_types=1);

/**
 * Phase 82 Test Suite: Enterprise Distributed Event Streaming Platform (Apache Kafka Architecture)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\Kafka\Consumer\Assignors\RangeAssignor;
use NetFusion\Core\Kafka\Consumer\Assignors\RoundRobinAssignor;
use NetFusion\Core\Kafka\Consumer\Assignors\StickyAssignor;
use NetFusion\Core\Kafka\Enums\Acks;
use NetFusion\Core\Kafka\Enums\AutoOffsetReset;
use NetFusion\Core\Kafka\Enums\CleanupPolicy;
use NetFusion\Core\Kafka\Enums\IsolationLevel;
use NetFusion\Core\Kafka\KafkaManager;
use NetFusion\Core\Kafka\Models\OffsetAndMetadata;
use NetFusion\Core\Kafka\Models\TopicPartition;
use NetFusion\Core\Kafka\Producer\IdempotentProducer;
use NetFusion\Core\Kafka\Producer\Partitioners\Murmur2Partitioner;
use NetFusion\Core\Kafka\Producer\Partitioners\RoundRobinPartitioner;
use NetFusion\Core\Kafka\Storage\LogSegment;
use NetFusion\Core\Kafka\Storage\OffsetIndex;
use NetFusion\Core\Kafka\Storage\PartitionLog;

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

echo "=== Testing Phase 82: Distributed Event Streaming Platform (Kafka Architecture) ===\n\n";

$kafka = new KafkaManager();

// Test 1: Topic Creation, Metadata & Partition Layout
$ordersTopic = $kafka->createTopic('orders-stream', partitions: 4, replicationFactor: 3, config: ['cleanup.policy' => 'compact']);
$desc = $kafka->describeTopic('orders-stream');
$allTopics = $kafka->listTopics();
assertTest(
    'Topic Creation & Metadata',
    $ordersTopic->name === 'orders-stream' &&
    $ordersTopic->partitionsCount === 4 &&
    $desc !== null &&
    $desc->cleanupPolicy() === CleanupPolicy::COMPACT &&
    count($ordersTopic->topicPartitions()) === 4 &&
    isset($allTopics['orders-stream']),
    "Partitions: {$ordersTopic->partitionsCount}, Cleanup: {$desc?->cleanupPolicy()->value}"
);

// Test 2: Partition Append-Only Commit Log & Monotonic Offsets
$tp0 = new TopicPartition('orders-stream', 0);
$log0 = $kafka->getLog($tp0);
$rec0 = $log0->append('order_created_1', key: 'usr-101', headers: ['trace_id' => 'abc']);
$rec1 = $log0->append('order_created_2', key: 'usr-102');
$rec2 = $log0->append('order_created_3', key: 'usr-101'); // Same key update
assertTest(
    'Append-Only Commit Log & Monotonic Offsets',
    $rec0->offset === 0 &&
    $rec1->offset === 1 &&
    $rec2->offset === 2 &&
    $log0->getLogEndOffset() === 3 &&
    $rec0->headers['trace_id'] === 'abc',
    "Offset0: {$rec0->offset}, Offset1: {$rec1->offset}, Offset2: {$rec2->offset}, LEO: {$log0->getLogEndOffset()}"
);

// Test 3: Log Segment Rollover & Offset Binary Search Index
$testTp = new TopicPartition('segment-test', 0);
// Create small segment capacity to force rollover
$smallLog = new PartitionLog($testTp, maxSegmentRecords: 3);
for ($i = 0; $i < 7; $i++) {
    $smallLog->append("payload_{$i}", "k{$i}");
}
$segments = $smallLog->getSegments();
$idx = $segments[0]->getIndex();
$pos = $idx->lookup(2);
assertTest(
    'Log Segment Rollover & Offset Index Lookup',
    count($segments) >= 3 &&
    $smallLog->getLogEndOffset() === 7 &&
    $pos !== null &&
    $pos === 2,
    "Segments count: " . count($segments) . ", LEO: {$smallLog->getLogEndOffset()}, Pos: {$pos}"
);

// Test 4: Partitioners (Murmur2 & RoundRobin)
$murmur = new Murmur2Partitioner();
$rr = new RoundRobinPartitioner();
$pA = $murmur->partition('orders-stream', 'customer-1234', 'payload', 4);
$pB = $murmur->partition('orders-stream', 'customer-1234', 'different_payload', 4);
$pC = $murmur->partition('orders-stream', 'customer-5678', 'payload', 4);
$rr0 = $rr->partition('orders-stream', null, 'val', 3);
$rr1 = $rr->partition('orders-stream', null, 'val', 3);
$rr2 = $rr->partition('orders-stream', null, 'val', 3);
$rr3 = $rr->partition('orders-stream', null, 'val', 3);
assertTest(
    'Partitioners (Murmur2 Deterministic Key Hash & RoundRobin Uniform Cycle)',
    $pA === $pB &&
    $rr0 === 0 &&
    $rr1 === 1 &&
    $rr2 === 2 &&
    $rr3 === 0,
    "Murmur pA: {$pA}, pB: {$pB}, RR: [{$rr0}, {$rr1}, {$rr2}, {$rr3}]"
);

// Test 5: Idempotent Producer & Sequence Counter
$idempotent = new IdempotentProducer(producerId: 5001, producerEpoch: 1);
$seq0 = $idempotent->nextSequence($tp0);
$seq1 = $idempotent->nextSequence($tp0);
$seq0_tp1 = $idempotent->nextSequence(new TopicPartition('orders-stream', 1));
$newEpoch = $idempotent->bumpEpoch();
$seqAfterBump = $idempotent->nextSequence($tp0);
assertTest(
    'Idempotent Producer Sequences & Epoch Escalation',
    $seq0 === 0 &&
    $seq1 === 1 &&
    $seq0_tp1 === 0 &&
    $newEpoch === 2 &&
    $seqAfterBump === 0,
    "PID: {$idempotent->getProducerId()}, Seq0: {$seq0}, Seq1: {$seq1}, New Epoch: {$newEpoch}, ResetSeq: {$seqAfterBump}"
);

// Test 6: High-Level Producer (KafkaProducer) Direct & Batch Send
$producer = $kafka->producer();
$pRec1 = $producer->send('orders-stream', 'payment_received', 'order-1', partition: 1);
$pRec2 = $producer->send('orders-stream', 'payment_settled', 'order-1', partition: 1);
$flushed = $producer->flush();
assertTest(
    'High-Level Producer Record Dispatch & Flush',
    $pRec1->partition === 1 &&
    $pRec1->offset === 0 &&
    $pRec2->offset === 1 &&
    $flushed >= 2,
    "Partition: {$pRec1->partition}, Offsets: [{$pRec1->offset}, {$pRec2->offset}], Flushed: {$flushed}"
);

// Test 7: Consumer Polling & Position Tracking
$consumerStandalone = $kafka->consumer(autoOffsetReset: AutoOffsetReset::EARLIEST);
$consumerStandalone->subscribe(['orders-stream']);
$polledRecords = $consumerStandalone->poll(timeoutMs: 50, maxRecords: 10);
$consumerStandalone->close();
assertTest(
    'Consumer Polling & Standalone Partition Retrieval',
    count($polledRecords) >= 5,
    "Polled count: " . count($polledRecords)
);

// Test 8: Consumer Group Rebalance - RangeAssignor
$rangeAssignor = new RangeAssignor();
$partsTopic = ['test-topic' => [
    new TopicPartition('test-topic', 0),
    new TopicPartition('test-topic', 1),
    new TopicPartition('test-topic', 2),
    new TopicPartition('test-topic', 3),
]];
$subs = [
    'consumer-1' => ['test-topic'],
    'consumer-2' => ['test-topic'],
];
$rangeAssigned = $rangeAssignor->assign($partsTopic, $subs);
assertTest(
    'Consumer Group Rebalance - RangeAssignor Contiguous Distribution',
    count($rangeAssigned['consumer-1']) === 2 &&
    count($rangeAssigned['consumer-2']) === 2 &&
    $rangeAssigned['consumer-1'][0]->partition === 0 &&
    $rangeAssigned['consumer-1'][1]->partition === 1 &&
    $rangeAssigned['consumer-2'][0]->partition === 2 &&
    $rangeAssigned['consumer-2'][1]->partition === 3,
    "C1: " . count($rangeAssigned['consumer-1']) . ", C2: " . count($rangeAssigned['consumer-2'])
);

// Test 9: Consumer Group Rebalance - RoundRobinAssignor & StickyAssignor
$rrAssignor = new RoundRobinAssignor();
$stickyAssignor = new StickyAssignor();
$rrAssigned = $rrAssignor->assign($partsTopic, $subs);
$stickyAssigned = $stickyAssignor->assign($partsTopic, $subs);
assertTest(
    'Consumer Group Rebalance - RoundRobin & Sticky Assignors',
    count($rrAssigned['consumer-1']) === 2 &&
    count($rrAssigned['consumer-2']) === 2 &&
    count($stickyAssigned['consumer-1']) === 2 &&
    count($stickyAssigned['consumer-2']) === 2,
    "RR: " . count($rrAssigned['consumer-1']) . ", Sticky: " . count($stickyAssigned['consumer-1'])
);

// Test 10: Consumer Group Offset Commit & Resumption
$kafka->createTopic('committed-topic', partitions: 1);
$kafka->produce('committed-topic', 'msg_0');
$kafka->produce('committed-topic', 'msg_1');
$kafka->produce('committed-topic', 'msg_2');

$cGroup = $kafka->consumer(groupId: 'billing-group', autoOffsetReset: AutoOffsetReset::EARLIEST, enableAutoCommit: false);
$cGroup->subscribe(['committed-topic']);
$batch1 = $cGroup->poll(timeoutMs: 50, maxRecords: 2);
// Commit offset 2
$cGroup->commitSync(['committed-topic-0' => 2]);
$cGroup->close();

// New consumer in same group should resume from offset 2
$cGroupResume = $kafka->consumer(groupId: 'billing-group', autoOffsetReset: AutoOffsetReset::EARLIEST);
$cGroupResume->subscribe(['committed-topic']);
$resumedBatch = $cGroupResume->poll(timeoutMs: 50, maxRecords: 10);
$cGroupResume->close();
assertTest(
    'Consumer Group Offset Commit & Position Resumption',
    count($batch1) === 2 &&
    count($resumedBatch) === 1 &&
    $resumedBatch[0]->offset === 2 &&
    $resumedBatch[0]->value === 'msg_2',
    "Batch1: " . count($batch1) . ", Resumed: " . count($resumedBatch) . ", Resumed offset: " . ($resumedBatch[0]->offset ?? 'none')
);

// Test 11: AutoOffsetReset Strategy (EARLIEST vs LATEST)
$cEarliest = $kafka->consumer(autoOffsetReset: AutoOffsetReset::EARLIEST);
$cEarliest->assign([new TopicPartition('committed-topic', 0)]);
$cLatest = $kafka->consumer(autoOffsetReset: AutoOffsetReset::LATEST);
$cLatest->assign([new TopicPartition('committed-topic', 0)]);

assertTest(
    'AutoOffsetReset Strategy (EARLIEST vs LATEST)',
    $cEarliest->position(new TopicPartition('committed-topic', 0)) === 0 &&
    $cLatest->position(new TopicPartition('committed-topic', 0)) === 3,
    "Earliest: {$cEarliest->position(new TopicPartition('committed-topic', 0))}, Latest: {$cLatest->position(new TopicPartition('committed-topic', 0))}"
);

// Test 12: Log Compaction (Deduplication by Key)
$compactTp = new TopicPartition('compact-test', 0);
$compactLog = new PartitionLog($compactTp);
$compactLog->append('v1_old', 'user_1');
$compactLog->append('v2_old', 'user_2');
$compactLog->append('v1_latest', 'user_1'); // Supersedes v1_old
$compactLog->append('unkeyed_msg', null);
$compactLog->append('v2_latest', 'user_2'); // Supersedes v2_old

$pruned = $compactLog->compact();
$remainingRecords = $compactLog->read(0, 100);
$remainingValues = array_map(fn($r) => $r->value, $remainingRecords);
assertTest(
    'Log Compaction (Deduplicating by Key, Preserving Latest State)',
    $pruned === 2 &&
    count($remainingRecords) === 3 &&
    in_array('v1_latest', $remainingValues, true) &&
    in_array('v2_latest', $remainingValues, true) &&
    in_array('unkeyed_msg', $remainingValues, true) &&
    !in_array('v1_old', $remainingValues, true),
    "Pruned: {$pruned}, Remaining: " . count($remainingRecords) . ", Vals: " . implode(', ', $remainingValues)
);

// Test 13: Log Retention Time-based Pruning
$retentionLog = new PartitionLog(new TopicPartition('retention-test', 0), maxSegmentRecords: 2);
for ($i = 0; $i < 6; $i++) {
    $retentionLog->append("item_{$i}");
}
$prunedByRetention = $kafka->getRetentionManager()->enforce(
    log: $retentionLog,
    retentionSeconds: 1, // Cutoff in 1s
    retentionBytes: 0,
    policy: CleanupPolicy::DELETE,
    now: time() + 10 // Simulate 10 seconds in future
);
assertTest(
    'Log Retention Time-based Pruning',
    $prunedByRetention >= 4 && $retentionLog->getLogStartOffset() >= 4,
    "Pruned: {$prunedByRetention}, Log start offset: {$retentionLog->getLogStartOffset()}"
);

// Test 14: Exactly-Once Semantics (EOS) & Transactional Isolation
$kafka->createTopic('txn-topic', partitions: 1);
$txProducer = $kafka->producer(transactionalId: 'finance-tx-producer');
$txProducer->initTransactions();

// Transaction 1: Committed
$txProducer->beginTransaction();
$txProducer->send('txn-topic', 'tx_committed_1', 'k1');
$txProducer->send('txn-topic', 'tx_committed_2', 'k2');
$txProducer->commitTransaction();

// Transaction 2: Aborted
$txProducer->beginTransaction();
$txProducer->send('txn-topic', 'tx_aborted_3', 'k3');
$txProducer->abortTransaction();

// Transaction 3: Committed
$txProducer->beginTransaction();
$txProducer->send('txn-topic', 'tx_committed_4', 'k4');
$txProducer->commitTransaction();

// Consumer with READ_COMMITTED should ONLY see committed messages (tx_committed_1, tx_committed_2, tx_committed_4)
$cCommitted = $kafka->consumer(autoOffsetReset: AutoOffsetReset::EARLIEST, isolation: IsolationLevel::READ_COMMITTED);
$cCommitted->assign([new TopicPartition('txn-topic', 0)]);
$committedRecords = $cCommitted->poll(timeoutMs: 50, maxRecords: 100);
$committedVals = array_map(fn($r) => $r->value, $committedRecords);

// Consumer with READ_UNCOMMITTED sees all non-control messages including aborted
$cUncommitted = $kafka->consumer(autoOffsetReset: AutoOffsetReset::EARLIEST, isolation: IsolationLevel::READ_UNCOMMITTED);
$cUncommitted->assign([new TopicPartition('txn-topic', 0)]);
$allRecords = $cUncommitted->poll(timeoutMs: 50, maxRecords: 100);
$allVals = array_map(fn($r) => $r->value, $allRecords);

assertTest(
    'Exactly-Once Semantics (EOS) Transactional Isolation (READ_COMMITTED vs READ_UNCOMMITTED)',
    count($committedRecords) === 3 &&
    in_array('tx_committed_1', $committedVals, true) &&
    in_array('tx_committed_2', $committedVals, true) &&
    in_array('tx_committed_4', $committedVals, true) &&
    !in_array('tx_aborted_3', $committedVals, true) &&
    count($allRecords) === 4 &&
    in_array('tx_aborted_3', $allVals, true),
    "Committed count: " . count($committedRecords) . " [" . implode(', ', $committedVals) . "], All count: " . count($allRecords)
);

// Test 15: Global Helpers & Dependency Injection
kafka_topic('helper-stream', partitions: 2);
$helperRecord = kafka_produce('helper-stream', 'helper_payload_1', key: 'h-1');
$consumedFromHelper = kafka_consume('helper-stream', maxRecords: 10);
assertTest(
    'Global Helpers (kafka, kafka_produce, kafka_consume, kafka_topic)',
    $helperRecord instanceof \NetFusion\Core\Kafka\Models\Record &&
    $helperRecord->value === 'helper_payload_1' &&
    count($consumedFromHelper) >= 1 &&
    $consumedFromHelper[0]->value === 'helper_payload_1',
    "Helper rec: {$helperRecord->value}, Consumed count: " . count($consumedFromHelper)
);

echo "\nSummary: {$passed} Passed, {$failed} Failed\n";
exit($failed === 0 ? 0 : 1);
