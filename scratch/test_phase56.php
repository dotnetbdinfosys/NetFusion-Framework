<?php

/**
 * DotNetBD Enterprise Framework
 * Phase 56: In-Memory Data Grid (IMDG) & Near-Cache Cluster Engine Test Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\IMDG\Compute\Aggregators\AverageAggregator;
use NetFusion\Core\IMDG\Compute\Aggregators\CountAggregator;
use NetFusion\Core\IMDG\Compute\Aggregators\SumAggregator;
use NetFusion\Core\IMDG\Compute\EntryProcessor;
use NetFusion\Core\IMDG\ContinuousQuery\EntryEvent;
use NetFusion\Core\IMDG\DistributedMap;
use NetFusion\Core\IMDG\Eviction\FifoEvictionPolicy;
use NetFusion\Core\IMDG\Eviction\LfuEvictionPolicy;
use NetFusion\Core\IMDG\Eviction\LruEvictionPolicy;
use NetFusion\Core\IMDG\GridCluster;
use NetFusion\Core\IMDG\IMDGManager;
use NetFusion\Core\IMDG\NearCache\NearCache;
use NetFusion\Core\IMDG\NearCache\NearCacheConfig;
use NetFusion\Core\IMDG\Partitioning\ConsistentHashRing;
use NetFusion\Core\IMDG\Partitioning\MemberNode;
use NetFusion\Core\IMDG\Partitioning\PartitionMap;
use NetFusion\Core\IMDG\Store\ArrayGridStore;
use NetFusion\Core\IMDG\Store\DatabaseGridStore;
use NetFusion\Core\IMDG\Vector\GridVectorIndex;

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
echo "   NetFusion Framework — Phase 56: In-Memory Data Grid (IMDG) Tests\n";
echo "=========================================================================\n\n";

// ---------------------------------------------------------
// Test Group 1: Eviction Policies (LRU, LFU, FIFO)
// ---------------------------------------------------------
echo "[1] Eviction Policies (LRU, LFU, FIFO)\n";

// LRU
$lru = new LruEvictionPolicy();
$lru->recordPut('k1');
usleep(1000);
$lru->recordPut('k2');
usleep(1000);
$lru->recordAccess('k1'); // k1 accessed later than k2, so k2 is least recently used
assert_test($lru->selectVictim() === 'k2', 'LRU selects least recently accessed key (k2)');

// LFU
$lfu = new LfuEvictionPolicy();
$lfu->recordPut('k1');
$lfu->recordPut('k2');
$lfu->recordAccess('k1');
$lfu->recordAccess('k1'); // k1 count: 3, k2 count: 1
assert_test($lfu->selectVictim() === 'k2', 'LFU selects least frequently accessed key (k2)');

// FIFO
$fifo = new FifoEvictionPolicy();
$fifo->recordPut('first');
usleep(1000);
$fifo->recordPut('second');
$fifo->recordAccess('first'); // access shouldn't change insertion order
assert_test($fifo->selectVictim() === 'first', 'FIFO selects oldest inserted key regardless of access (first)');

// ---------------------------------------------------------
// Test Group 2: L1 Near-Cache Engine
// ---------------------------------------------------------
echo "\n[2] L1 Near-Cache Engine\n";

$ncConf = new NearCacheConfig(maxSize: 3, ttlSeconds: 2, ttiSeconds: 1, evictionPolicy: 'lru');
$nearCache = new NearCache('user_cache', $ncConf);

$nearCache->put('u1', ['name' => 'Alice']);
$nearCache->put('u2', ['name' => 'Bob']);
$nearCache->put('u3', ['name' => 'Charlie']);

assert_test($nearCache->get('u1')['name'] === 'Alice', 'NearCache retrieves cached entry (u1)');
assert_test($nearCache->get('non_existent') === null, 'NearCache returns null on cache miss');

// Eviction on maxSize exceed
$nearCache->put('u4', ['name' => 'Dave']); // should evict LRU victim (u2, since u1 was accessed)
assert_test($nearCache->size() === 3, 'NearCache enforces maxSize constraint (size: 3)');
assert_test($nearCache->get('u2') === null, 'LRU victim (u2) was evicted from NearCache');

// Invalidation
$nearCache->invalidate('u1');
assert_test($nearCache->get('u1') === null, 'NearCache invalidates entry on demand');

$stats = $nearCache->getStats();
assert_test($stats['hits'] >= 1 && $stats['evictions'] >= 1 && $stats['invalidations'] >= 1, 'NearCache tracks accurate metrics (hits, evictions, invalidations)');

// ---------------------------------------------------------
// Test Group 3: Consistent Hashing Ring & PartitionMap
// ---------------------------------------------------------
echo "\n[3] Consistent Hashing Ring & Partition Routing\n";

$ring = new ConsistentHashRing(vnodes: 50);
$node1 = new MemberNode('node-1', '10.0.0.1', 5701, weight: 1);
$node2 = new MemberNode('node-2', '10.0.0.2', 5701, weight: 1);
$node3 = new MemberNode('node-3', '10.0.0.3', 5701, weight: 2);

$ring->addNode($node1)->addNode($node2)->addNode($node3);

$targetNode = $ring->getNode('order_12345');
assert_test($targetNode !== null && in_array($targetNode->getId(), ['node-1', 'node-2', 'node-3'], true), 'ConsistentHashRing routes key to a cluster member node');

$backups = $ring->getBackupNodes('order_12345', 2);
assert_test(count($backups) === 2 && $backups[0]->getId() !== $targetNode->getId(), 'ConsistentHashRing resolves distinct replica backup nodes');

$partMap = new PartitionMap(partitionCount: 16, backupCount: 1, ring: $ring);
$partMap->rebalance([$node1, $node2, $node3]);

$part = $partMap->getPartitionForKey('session_key_99');
assert_test($part->getPrimaryOwnerId() !== null, 'PartitionMap assigns primary owner node to partition');
assert_test(count($part->getBackupOwnerIds()) === 1, 'PartitionMap assigns backup replica owner');

// ---------------------------------------------------------
// Test Group 4: DistributedMap Multi-Tier Operations
// ---------------------------------------------------------
echo "\n[4] DistributedMap Multi-Tier Operations\n";

$dNearCache = new NearCache('orders', new NearCacheConfig(maxSize: 100));
$dMap = new DistributedMap('orders', $partMap, $dNearCache);

$dMap->put('ord_1', ['item' => 'MacBook', 'price' => 1999.0]);
$dMap->put('ord_2', ['item' => 'Keyboard', 'price' => 149.0]);

// First read: populated from L2 partition into L1 near-cache
$v1 = $dMap->get('ord_1');
assert_test($v1['item'] === 'MacBook', 'DistributedMap retrieves entry from L2 Partition');

// Second read: served directly from L1 near-cache
$v1L1 = $dMap->get('ord_1');
assert_test($v1L1['item'] === 'MacBook', 'DistributedMap serves repeated read from L1 Near-Cache');

$metrics = $dMap->getMetrics();
assert_test($metrics['l1NearCacheHits'] >= 1 && $metrics['l2PartitionHits'] >= 1, 'DistributedMap records L1 and L2 tier hits');

assert_test($dMap->putIfAbsent('ord_1', ['item' => 'Duplicate']) === false, 'putIfAbsent returns false for existing key');
assert_test($dMap->putIfAbsent('ord_3', ['item' => 'Mouse', 'price' => 79.0]) === true, 'putIfAbsent succeeds for new key');

assert_test($dMap->size() === 3, 'DistributedMap size() reports 3 items');
assert_test($dMap->containsKey('ord_2') === true, 'DistributedMap containsKey() returns true');

$dMap->remove('ord_2');
assert_test($dMap->containsKey('ord_2') === false, 'DistributedMap remove() deletes key from L1 & L2');

// ---------------------------------------------------------
// Test Group 5: Atomic EntryProcessor & In-Place Mutation
// ---------------------------------------------------------
echo "\n[5] Atomic EntryProcessor & In-Place Mutation\n";

$dMap->put('counter', 10);

$incProcessor = new EntryProcessor(function (string $k, mixed &$v) {
    $v = ((int) $v) + 5;
    return $v;
});

$result = $dMap->executeOnKey('counter', $incProcessor);
assert_test($result === 15, 'EntryProcessor returned mutated result (15)');
assert_test($dMap->get('counter') === 15, 'EntryProcessor modified value in-place in partition and near-cache (15)');

// ---------------------------------------------------------
// Test Group 6: Continuous Query & Event Stream Engine
// ---------------------------------------------------------
echo "\n[6] Continuous Query & Real-Time Event Stream\n";

$capturedEvents = [];
$listenerId = $dMap->addEntryListener(function (EntryEvent $evt) use (&$capturedEvents) {
    $capturedEvents[] = $evt;
});

$dMap->put('cq_item', 'Initial Value');
$dMap->put('cq_item', 'Updated Value');
$dMap->remove('cq_item');

assert_test(count($capturedEvents) === 3, 'Continuous query captured all 3 mutation events');
assert_test($capturedEvents[0]->getEventType() === EntryEvent::TYPE_ADDED, 'Event 1 is TYPE_ADDED');
assert_test($capturedEvents[1]->getEventType() === EntryEvent::TYPE_UPDATED && $capturedEvents[1]->getOldValue() === 'Initial Value', 'Event 2 is TYPE_UPDATED with oldValue');
assert_test($capturedEvents[2]->getEventType() === EntryEvent::TYPE_REMOVED, 'Event 3 is TYPE_REMOVED');

$dMap->removeEntryListener($listenerId);

// ---------------------------------------------------------
// Test Group 7: Distributed Compute Aggregators
// ---------------------------------------------------------
echo "\n[7] Distributed Compute Aggregators\n";

$dMap->clear();
$dMap->put('prod_1', ['name' => 'Phone', 'price' => 800.0, 'active' => true]);
$dMap->put('prod_2', ['name' => 'Tablet', 'price' => 600.0, 'active' => true]);
$dMap->put('prod_3', ['name' => 'Cable', 'price' => 20.0, 'active' => false]);

$totalSum = SumAggregator::aggregate($dMap, 'price');
assert_test($totalSum === 1420.0, 'SumAggregator computes total price (1420.0)');

$activeCount = CountAggregator::aggregate($dMap, fn($item) => !empty($item['active']));
assert_test($activeCount === 2, 'CountAggregator filters active products (count: 2)');

$avgPrice = AverageAggregator::aggregate($dMap, 'price');
assert_test(round($avgPrice, 2) === 473.33, 'AverageAggregator calculates mean price (473.33)');

// ---------------------------------------------------------
// Test Group 8: In-Memory Vector Search Index Layer
// ---------------------------------------------------------
echo "\n[8] In-Memory Vector Search Index Layer\n";

$vecIndex = new GridVectorIndex(metric: 'cosine');
$vecIndex->index('doc_ai', [1.0, 0.0, 0.0], ['topic' => 'AI']);
$vecIndex->index('doc_database', [0.0, 1.0, 0.0], ['topic' => 'Database']);
$vecIndex->index('doc_ml', [0.9, 0.1, 0.0], ['topic' => 'ML']);

$searchResults = $vecIndex->search([1.0, 0.0, 0.0], topK: 2);
assert_test(count($searchResults) === 2, 'GridVectorIndex returns top 2 nearest neighbors');
assert_test($searchResults[0]['key'] === 'doc_ai' && $searchResults[0]['score'] >= 0.99, 'Nearest neighbor match is doc_ai (cosine: 1.0)');
assert_test($searchResults[1]['key'] === 'doc_ml', 'Second nearest match is doc_ml');

// ---------------------------------------------------------
// Test Group 9: Persistence Stores (Write-Through / Behind & Database)
// ---------------------------------------------------------
echo "\n[9] Persistence Stores (Write-Through / Write-Behind & SQLite)\n";

// SQLite DB Store Test
$sqlitePdo = new PDO('sqlite::memory:');
$migration = require __DIR__ . '/../database/migrations/2026_09_21_000036_create_imdg_cluster_tables.php';
$migration->up($sqlitePdo);

$dbStore = new DatabaseGridStore($sqlitePdo);
$dbStore->store('users', 'u101', ['name' => 'Ashish', 'role' => 'Architect']);

$loaded = $dbStore->load('users', 'u101');
assert_test($loaded !== null && $loaded['name'] === 'Ashish', 'DatabaseGridStore persists and reloads JSON payload');

// Write-Through Map
$wtMap = new DistributedMap('wt_map', $partMap, null, $dbStore, 'write-through');
$wtMap->put('k_wt', 'Persistent Value');
assert_test($dbStore->load('wt_map', 'k_wt') === 'Persistent Value', 'Write-Through stores synchronously to database');

// Write-Behind Map
$wbMap = new DistributedMap('wb_map', $partMap, null, $dbStore, 'write-behind');
$wbMap->put('k_wb1', 'Val 1');
$wbMap->put('k_wb2', 'Val 2');
assert_test($dbStore->load('wb_map', 'k_wb1') === null, 'Write-Behind does not immediately write to database');
$flushed = $wbMap->flushWriteBehind();
assert_test($flushed === 2 && $dbStore->load('wb_map', 'k_wb1') === 'Val 1', 'flushWriteBehind() batches pending writes to store');

// ---------------------------------------------------------
// Test Group 10: GridCluster, IMDGManager & Global Helpers
// ---------------------------------------------------------
echo "\n[10] GridCluster, IMDGManager & Global Helpers\n";

$cluster = new GridCluster('prod-cluster', $node1, partitionCount: 32, backupCount: 1, persistenceStore: new ArrayGridStore());
$cluster->addMember($node2);

assert_test(count($cluster->getMembers()) === 2, 'GridCluster registers multiple members');

$ordersMap = $cluster->getMap('orders');
$ordersMap->put('ORD-901', ['total' => 350.0]);
assert_test($ordersMap->get('ORD-901')['total'] === 350.0, 'GridCluster map operations functional');

$clusterStats = $cluster->getStats();
assert_test($clusterStats['totalMembers'] === 2 && $clusterStats['totalMaps'] === 1, 'GridCluster aggregates statistics');

// IMDGManager & Helpers
$imdgMgr = new IMDGManager([
    'clusters' => [
        'default' => [
            'name' => 'test-grid',
            'near_cache' => ['enabled' => true, 'max_size' => 1000],
        ],
    ],
]);

assert_test($imdgMgr->cluster() instanceof GridCluster, 'IMDGManager returns default GridCluster');

$imdgMgr->put('settings', 'site_name', 'NetFusion Enterprise');
assert_test($imdgMgr->get('settings', 'site_name') === 'NetFusion Enterprise', 'IMDGManager put/get shortcut operates properly');

// Test global helpers
assert_test(function_exists('imdg') && function_exists('grid_map') && function_exists('grid_get') && function_exists('grid_put'), 'Global helpers imdg(), grid_map(), grid_get(), grid_put() exist');
assert_test(grid_put('global_test', 'alpha', 'beta') === null, 'grid_put() helper stores entry');
assert_test(grid_get('global_test', 'alpha') === 'beta', 'grid_get() helper retrieves entry');

echo "\n=========================================================================\n";
echo "   Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "=========================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
