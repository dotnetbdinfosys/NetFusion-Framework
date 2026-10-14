<?php

/**
 * DotNetBD Enterprise Framework
 *
 * Phase 49 Test Suite: Multi-Region Edge Data Replication, CRDT & Geo-Routing Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Data\Crdt\GSet;
use NetFusion\Core\Data\Crdt\LWWMap;
use NetFusion\Core\Data\Crdt\LWWRegister;
use NetFusion\Core\Data\Crdt\MVRegister;
use NetFusion\Core\Data\Crdt\ORSet;
use NetFusion\Core\Data\Crdt\PNCounter;
use NetFusion\Core\Data\Crdt\VectorClock;
use NetFusion\Core\Data\Replication\RegionNode;
use NetFusion\Core\Data\Replication\ReplicationEvent;
use NetFusion\Core\Data\Replication\ReplicationManager;
use NetFusion\Core\Data\Replication\Store\ArrayReplicationStore;
use NetFusion\Core\Data\Replication\Store\DatabaseReplicationStore;
use NetFusion\Core\Http\Middleware\GeoRoutingMiddleware;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

echo "\n=======================================================\n";
echo "   NetFusion Phase 49: Edge Replication & CRDT Suite   \n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, ?string $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "✔ [PASS] {$description}\n";
    } else {
        $failed++;
        echo "✖ [FAIL] {$description}" . ($detail ? " — {$detail}" : '') . "\n";
    }
}

// -----------------------------------------------------------------------------
// 1. Vector Clock & Causality Tests
// -----------------------------------------------------------------------------
echo "\n--- 1. Vector Clock & Causality Engine ---\n";

$vcA = new VectorClock(['us-east' => 1]);
$vcB = new VectorClock(['us-east' => 1, 'eu-west' => 1]);
$vcC = new VectorClock(['us-east' => 2]);

assertTest('VectorClock: Increment advances node counter', $vcA->increment('us-east')->get('us-east') === 2);
assertTest('VectorClock: happensBefore detects strict causality (vcB precedes modified vcA)', (new VectorClock(['us-east' => 1]))->happensBefore($vcB));
assertTest('VectorClock: isConcurrent detects concurrent divergent updates', $vcB->isConcurrent($vcC));

$vcMerged = (new VectorClock(['us-east' => 1, 'eu-west' => 3]))->merge(new VectorClock(['us-east' => 4, 'eu-west' => 2, 'ap-south' => 1]));
assertTest('VectorClock: merge takes component-wise maximum',
    $vcMerged->get('us-east') === 4 &&
    $vcMerged->get('eu-west') === 3 &&
    $vcMerged->get('ap-south') === 1
);

// -----------------------------------------------------------------------------
// 2. PNCounter (Positive-Negative Counter) CRDT Tests
// -----------------------------------------------------------------------------
echo "\n--- 2. PNCounter CRDT ---\n";

$counterA = new PNCounter([], [], 'us-east');
$counterB = new PNCounter([], [], 'eu-west');

$counterA->increment(10);
$counterA->decrement(3);
assertTest('PNCounter: Local increments and decrements calculate value correctly', $counterA->getValue() === 7);

$counterB->increment(20);
$counterB->decrement(5);

// Cross-replica merge
$counterA->merge($counterB);
assertTest('PNCounter: Merged counter combines multi-region increments/decrements (7 + 15 = 22)', $counterA->getValue() === 22);

// Associative & Commutative test
$counterC = new PNCounter([], [], 'ap-south');
$counterC->increment(100);

$mergedAB_C = (clone $counterA)->merge($counterC);
$mergedC_AB = (clone $counterC)->merge($counterA);
assertTest('PNCounter: Merge is strictly commutative', $mergedAB_C->getValue() === $mergedC_AB->getValue() && $mergedAB_C->getValue() === 122);

// Rehydrate from state
$counterFromState = PNCounter::fromState($counterA->getState());
assertTest('PNCounter: Rehydrates identically from state payload', $counterFromState->getValue() === 22);

// -----------------------------------------------------------------------------
// 3. GSet (Grow-Only Set) CRDT Tests
// -----------------------------------------------------------------------------
echo "\n--- 3. GSet CRDT ---\n";

$gset1 = new GSet(['apple', 'banana']);
$gset2 = new GSet(['banana', 'cherry', 'date']);

$gset1->merge($gset2);
$elements = $gset1->getValue();
sort($elements);
assertTest('GSet: Merges elements via set union without duplicates', $elements === ['apple', 'banana', 'cherry', 'date']);
assertTest('GSet: contains() returns true for present element', $gset1->contains('cherry') && !$gset1->contains('fig'));

// -----------------------------------------------------------------------------
// 4. ORSet (Observed-Remove Set with Add-Wins) CRDT Tests
// -----------------------------------------------------------------------------
echo "\n--- 4. ORSet (Add-Wins Set) CRDT ---\n";

$orsetA = new ORSet(['laptop', 'mouse']);
$orsetB = new ORSet();

// A removes 'mouse'
$orsetA->remove('mouse');
assertTest('ORSet: Element removal removes active item', !$orsetA->contains('mouse') && $orsetA->contains('laptop'));

// B adds 'keyboard'
$orsetB->add('keyboard');

// Merge A and B
$orsetA->merge($orsetB);
assertTest('ORSet: Merged set contains laptop and keyboard, but not removed mouse',
    $orsetA->contains('laptop') &&
    $orsetA->contains('keyboard') &&
    !$orsetA->contains('mouse')
);

// Add-Wins Concurrent Behavior:
// Replica 1 removes 'laptop', Replica 2 adds 'laptop' concurrently
$r1 = clone $orsetA;
$r2 = clone $orsetA;

$r1->remove('laptop');
$r2->add('laptop'); // Generates new tag

$r1->merge($r2);
assertTest('ORSet: Concurrent Add-Wins semantics preserves newly added element', $r1->contains('laptop'));

// -----------------------------------------------------------------------------
// 5. LWWRegister (Last-Write-Wins Register) CRDT Tests
// -----------------------------------------------------------------------------
echo "\n--- 5. LWWRegister CRDT ---\n";

$lww1 = new LWWRegister('v1', 100.0, 'us-east');
$lww2 = new LWWRegister('v2', 200.0, 'eu-west');

$lww1->merge($lww2);
assertTest('LWWRegister: Higher timestamp wins merge (v2 > v1)', $lww1->getValue() === 'v2');

// Equal timestamp tie-breaker on nodeId string comparison ('us-east' > 'eu-west')
$lwwTieA = new LWWRegister('val-eu', 150.0, 'eu-west');
$lwwTieB = new LWWRegister('val-us', 150.0, 'us-east');
$lwwTieA->merge($lwwTieB);
assertTest('LWWRegister: Equal timestamp tie-breaker deterministically picks higher node ID', $lwwTieA->getValue() === 'val-us');

// -----------------------------------------------------------------------------
// 6. MVRegister (Multi-Value Register) CRDT Tests
// -----------------------------------------------------------------------------
echo "\n--- 6. MVRegister CRDT ---\n";

$mv1 = new MVRegister();
$mv1->set('CartA', 'us-east');

$mv2 = new MVRegister();
$mv2->set('CartB', 'eu-west');

// Merge concurrent branches
$mv1->merge($mv2);
$mvVals = $mv1->getValue();
sort($mvVals);
assertTest('MVRegister: Retains concurrent divergent writes as multi-value branch', $mv1->isConflicted() && $mvVals === ['CartA', 'CartB']);

// Advance causality on one branch: resolution overwrite
$mv1->set('CartResolved', 'us-east');
assertTest('MVRegister: Causal write supersedes prior conflicting versions', !$mv1->isConflicted() && $mv1->getValue() === ['CartResolved']);

// -----------------------------------------------------------------------------
// 7. LWWMap (Last-Write-Wins Map) CRDT Tests
// -----------------------------------------------------------------------------
echo "\n--- 7. LWWMap CRDT ---\n";

$mapA = new LWWMap('us-east');
$mapA->set('title', 'NetFusion Enterprise', 10.0);
$mapA->set('tier', 'standard', 10.0);

$mapB = new LWWMap('eu-west');
$mapB->set('tier', 'enterprise_gold', 20.0); // newer update
$mapB->remove('title', 15.0);               // newer deletion

$mapA->merge($mapB);
assertTest('LWWMap: Merges key updates and deletions with timestamp precedence',
    $mapA->get('tier') === 'enterprise_gold' &&
    !$mapA->has('title') &&
    $mapA->get('title') === null
);

// -----------------------------------------------------------------------------
// 8. Multi-Region Edge Replication Manager & Delta Sync
// -----------------------------------------------------------------------------
echo "\n--- 8. Multi-Region Replication Manager & Delta Sync ---\n";

$replStore = new ArrayReplicationStore();
$replManager = new ReplicationManager(
    store: $replStore,
    config: [
        'local_region' => 'us-east',
        'regions' => [
            'us-east' => ['name' => 'US East', 'countries' => ['US', 'CA'], 'latency_ms' => 10.0],
            'eu-west' => ['name' => 'EU West', 'countries' => ['DE', 'GB', 'FR'], 'latency_ms' => 25.0],
            'ap-south' => ['name' => 'AP South', 'countries' => ['BD', 'IN', 'SG'], 'latency_ms' => 45.0],
        ]
    ]
);

assertTest('ReplicationManager: Correctly initializes local region and nodes',
    $replManager->getLocalRegionId() === 'us-east' &&
    count($replManager->getNodes()) === 3 &&
    $replManager->getNode('eu-west') !== null
);

// Record Local Change Event
$event = $replManager->recordChange('inventory', 'item_101', 'UPDATE', ['stock' => 50]);
assertTest('ReplicationManager: Records outbound change delta with vector clock',
    $event instanceof ReplicationEvent &&
    $event->originRegion === 'us-east' &&
    $replStore->count() === 1
);

// Simulate Remote Incoming CRDT Merge Delta from eu-west
$remoteCounter = new PNCounter([], [], 'eu-west');
$remoteCounter->increment(75);

$remoteEvent = ReplicationEvent::create(
    originRegion: 'eu-west',
    entityType: 'crdt',
    entityId: 'global_page_views',
    operation: ReplicationEvent::OP_CRDT_MERGE,
    payload: ['crdt_state' => $remoteCounter->getState()],
    vectorClock: (new VectorClock(['eu-west' => 5]))
);

$deltaResult = $replManager->applyDelta([$remoteEvent]);
assertTest('ReplicationManager: Applies remote incoming CRDT merge delta',
    $deltaResult['applied'] === 1 &&
    $replManager->crdt('global_page_views')->getValue() === 75
);

// Sync Outbound Changelog
$syncResult = $replManager->syncWith('eu-west');
assertTest('ReplicationManager: syncWith drains pending changelog events',
    $syncResult['status'] === 'success' &&
    $syncResult['synced'] === 1 &&
    count($replStore->getPending()) === 0
);

// -----------------------------------------------------------------------------
// 9. Geo-Routing Middleware & Proximity Resolution
// -----------------------------------------------------------------------------
echo "\n--- 9. Geo-Routing Middleware & Proximity Resolution ---\n";

assertTest('Geo-Routing: Resolves Germany (DE) to EU West region', $replManager->resolveNearestRegion('DE')->id === 'eu-west');
assertTest('Geo-Routing: Resolves Bangladesh (BD) to AP South region', $replManager->resolveNearestRegion('BD')->id === 'ap-south');
assertTest('Geo-Routing: Resolves Canada (CA) to US East region', $replManager->resolveNearestRegion('CA')->id === 'us-east');

$geoMiddleware = new GeoRoutingMiddleware($replManager);
$reqGeo = Request::create('GET', '/api/v1/products', [], ['HTTP_CF_IPCOUNTRY' => 'DE']);
$resGeo = $geoMiddleware->handle($reqGeo, fn(Request $r) => new Response('OK', 200));

assertTest('GeoRoutingMiddleware: Injects X-Edge-Region header into response',
    $resGeo->getHeader('X-Edge-Region') === 'eu-west' &&
    $reqGeo->getAttribute('edge_region') === 'eu-west'
);

// -----------------------------------------------------------------------------
// 10. Database Replication Store & Migration Tests
// -----------------------------------------------------------------------------
echo "\n--- 10. Database Store & Migration ---\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_20_000029_create_replication_changelog_tables.php';
$migration->up($pdo);

$dbEvent = ReplicationEvent::create('us-east', 'inventory', 'item_101', 'UPDATE', ['stock' => 50]);
$dbStore = new DatabaseReplicationStore($pdo);
$dbStore->record($dbEvent);
assertTest('Database Store: Persists changelog delta into SQLite table', $dbStore->count() === 1);

$pendingDb = $dbStore->getPending();
assertTest('Database Store: Retrieves pending changelog events with vector clock',
    count($pendingDb) === 1 &&
    $pendingDb[0]->entityId === 'item_101'
);

$dbStore->markSynced($dbEvent->id);
assertTest('Database Store: Marks changelog event as synced', count($dbStore->getPending()) === 0);

// Migration Down
$migration->down($pdo);
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_replication_changelog'");
$dropped = $stmt && $stmt->fetchColumn() === false;
$stmt->closeCursor();
unset($stmt);
assertTest('Database Migration: netfusion_replication_changelog dropped cleanly on down()', $dropped);

// -----------------------------------------------------------------------------
// 11. Global Helpers & DI Resolution
// -----------------------------------------------------------------------------
echo "\n--- 11. Global Helpers & DI Resolution ---\n";

$app = new Application(dirname(__DIR__));
$app->instance(ReplicationManager::class, $replManager);
$app->instance('replication', $replManager);

assertTest('Global Helper: replication() returns ReplicationManager', replication() instanceof ReplicationManager);
assertTest('Global Helper: crdt() retrieves or registers named CRDT', crdt('test_counter') instanceof PNCounter);
assertTest('Global Helper: vector_clock() constructs VectorClock', vector_clock(['us-east' => 1]) instanceof VectorClock);

echo "\n=======================================================\n";
echo "   Phase 49 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
