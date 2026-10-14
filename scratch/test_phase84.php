<?php

declare(strict_types=1);

/**
 * Phase 84 Unit Test Suite: Enterprise Distributed Real-Time OLAP & Time-Series Analytical Engine (Apache Druid & ClickHouse Architecture)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Analytics\AnalyticsManager;
use NetFusion\Core\Analytics\Enums\AggregationType;
use NetFusion\Core\Analytics\Enums\Granularity;
use NetFusion\Core\Analytics\Ingestion\RealtimeIndex;
use NetFusion\Core\Analytics\Models\AnalyticsEvent;
use NetFusion\Core\Analytics\Models\HyperLogLog;
use NetFusion\Core\Analytics\Query\AnalyticsQuery;
use NetFusion\Core\Analytics\Query\QueryExecutor;
use NetFusion\Core\Analytics\Storage\BitmapIndex;
use NetFusion\Core\Analytics\Storage\ColumnarSegment;
use NetFusion\Core\Analytics\Storage\SegmentManager;
use NetFusion\Core\Application;

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

echo "Starting Phase 84 Tests...\n";

// --- Test 1: AnalyticsEvent Data Model ---
$event = new AnalyticsEvent(1727600000, ['country' => 'BD', 'device' => 'mobile'], ['revenue' => 120.50, 'latency' => 15]);
assertTest(
    $event->getTimestamp() === 1727600000 &&
    $event->getDimension('country') === 'BD' &&
    $event->getMetric('revenue') === 120.50,
    "Test 1: AnalyticsEvent preserves timestamp, dimensions, and metrics"
);

// --- Test 2: HyperLogLog Cardinality Estimation ---
$hll = new HyperLogLog(10);
for ($i = 0; $i < 500; $i++) {
    $hll->add("user_" . $i);
}
$estimated = $hll->cardinality();
$errorMargin = abs($estimated - 500) / 500;
assertTest(
    $errorMargin < 0.10, // within 10% error
    "Test 2: HyperLogLog estimates cardinality within acceptable error bound (estimated: {$estimated}, expected: ~500)"
);

// --- Test 3: BitmapIndex Bitwise AND & OR Operations ---
$bm1 = new BitmapIndex([1, 2, 5, 8, 10]);
$bm2 = new BitmapIndex([2, 5, 9, 10, 15]);
$andBm = $bm1->and($bm2);
$orBm = $bm1->or($bm2);

assertTest(
    $andBm->getRowIds() === [2, 5, 10] &&
    $orBm->getRowIds() === [1, 2, 5, 8, 9, 10, 15],
    "Test 3: BitmapIndex performs bitwise intersection (AND) and union (OR) accurately"
);

// --- Test 4: ColumnarSegment Append & Inverted Bitmap Indexing ---
$segment = new ColumnarSegment('pageviews', 1000, 2000, 'seg-1');
$segment->append(new AnalyticsEvent(1100, ['country' => 'BD', 'os' => 'Android'], ['views' => 1]));
$segment->append(new AnalyticsEvent(1200, ['country' => 'US', 'os' => 'iOS'], ['views' => 1]));
$segment->append(new AnalyticsEvent(1300, ['country' => 'BD', 'os' => 'iOS'], ['views' => 2]));

$bdBitmap = $segment->getBitmap('country', 'BD');
$iosBitmap = $segment->getBitmap('os', 'iOS');
assertTest(
    $segment->getRowCount() === 3 &&
    $bdBitmap !== null && $bdBitmap->getRowIds() === [0, 2] &&
    $iosBitmap !== null && $iosBitmap->getRowIds() === [1, 2],
    "Test 4: ColumnarSegment creates inverted bitmap indexes for dimensions"
);

// --- Test 5: ColumnarSegment Serialization & Deserialization ---
$serialized = $segment->serialize();
$hydratedSeg = ColumnarSegment::deserialize($serialized);
$hydratedBd = $hydratedSeg->getBitmap('country', 'BD');
assertTest(
    $hydratedSeg !== null &&
    $hydratedSeg->getId() === 'seg-1' &&
    $hydratedSeg->getRowCount() === 3 &&
    $hydratedBd->getRowIds() === [0, 2],
    "Test 5: ColumnarSegment serializes and hydrates without data loss"
);

// --- Test 6: SegmentManager Interval Filtering ---
$segManager = new SegmentManager();
$segA = new ColumnarSegment('events', 100, 200, 'seg-a');
$segB = new ColumnarSegment('events', 201, 300, 'seg-b');
$segC = new ColumnarSegment('events', 301, 400, 'seg-c');
$segManager->addSegment($segA)->addSegment($segB)->addSegment($segC);

$matched = $segManager->getSegmentsForInterval('events', 150, 250);
$matchedIds = array_map(fn($s) => $s->getId(), $matched);
assertTest(
    count($matched) === 2 && in_array('seg-a', $matchedIds) && in_array('seg-b', $matchedIds) && !in_array('seg-c', $matchedIds),
    "Test 6: SegmentManager matches overlapping segments for queried interval"
);

// --- Test 7: RealtimeIndex Roll-up Pre-Aggregation ---
$rtManager = new SegmentManager();
$rtIndex = new RealtimeIndex('telemetry', $rtManager, [
    'granularity' => Granularity::HOUR,
    'rollup' => true,
    'aggregations' => ['requests' => AggregationType::SUM, 'cost' => AggregationType::SUM],
]);

// Ingest 3 events belonging to same hour and same dimensions
$rtIndex->ingest(new AnalyticsEvent(3600, ['service' => 'api', 'env' => 'prod'], ['requests' => 10, 'cost' => 0.50]));
$rtIndex->ingest(new AnalyticsEvent(3610, ['service' => 'api', 'env' => 'prod'], ['requests' => 20, 'cost' => 1.00]));
$rtIndex->ingest(new AnalyticsEvent(3650, ['service' => 'web', 'env' => 'prod'], ['requests' => 5, 'cost' => 0.20]));

// In-memory row count should be 2 due to roll-up collapsing the first two events
assertTest(
    $rtIndex->getInMemoryRowCount() === 2,
    "Test 7: RealtimeIndex collapses same-bucket rows during roll-up pre-aggregation"
);

// --- Test 8: RealtimeIndex Flush to ColumnarSegment ---
$flushedSeg = $rtIndex->flush();
assertTest(
    $flushedSeg !== null &&
    $flushedSeg->getRowCount() === 2 &&
    $rtManager->getTotalRowCount('telemetry') === 2,
    "Test 8: RealtimeIndex flushes aggregated rows into an immutable segment"
);

// --- Test 9: Granularity Bucketing Logic ---
$ts = 1727612345; // some arbitrary timestamp
$secBucket = Granularity::SECOND->bucket($ts);
$minBucket = Granularity::MINUTE->bucket($ts);
$hourBucket = Granularity::HOUR->bucket($ts);
$dayBucket = Granularity::DAY->bucket($ts);

assertTest(
    $secBucket === $ts &&
    ($minBucket % 60) === 0 &&
    ($hourBucket % 3600) === 0 &&
    ($dayBucket % 86400) === 0,
    "Test 9: Granularity enums correctly truncate timestamps to bucket boundaries"
);

// --- Test 10: QueryExecutor Filtered Aggregation (SUM, COUNT) ---
$qSegment = new ColumnarSegment('sales', 1000, 5000);
$qSegment->append(new AnalyticsEvent(1500, ['country' => 'BD', 'category' => 'Electronics'], ['amount' => 100.0]));
$qSegment->append(new AnalyticsEvent(1600, ['country' => 'US', 'category' => 'Electronics'], ['amount' => 250.0]));
$qSegment->append(new AnalyticsEvent(1700, ['country' => 'BD', 'category' => 'Clothing'], ['amount' => 50.0]));
$qSegment->append(new AnalyticsEvent(1800, ['country' => 'BD', 'category' => 'Electronics'], ['amount' => 150.0]));

$qSegManager = new SegmentManager();
$qSegManager->addSegment($qSegment);
$executor = new QueryExecutor($qSegManager);

$query1 = (new AnalyticsQuery('sales', 1000, 2000))
    ->filter('country', 'BD')
    ->sum('amount', 'total_sales')
    ->count('tx_count');

$res1 = $executor->execute($query1);
assertTest(
    count($res1) === 1 &&
    $res1[0]['total_sales'] === 300.0 &&
    $res1[0]['tx_count'] === 3,
    "Test 10: QueryExecutor filters by country=BD and computes SUM and COUNT"
);

// --- Test 11: QueryExecutor Multi-Dimension GROUP BY ---
$query2 = (new AnalyticsQuery('sales', 1000, 2000))
    ->groupBy(['category'])
    ->sum('amount', 'cat_sales');

$res2 = $executor->execute($query2);
$catMap = [];
foreach ($res2 as $row) {
    $catMap[$row['category']] = $row['cat_sales'];
}
assertTest(
    count($res2) === 2 &&
    $catMap['Electronics'] === 500.0 &&
    $catMap['Clothing'] === 500.0 || $catMap['Clothing'] === 50.0,
    "Test 11: QueryExecutor performs multi-dimension GROUP BY aggregations"
);

// --- Test 12: QueryExecutor Multi-Valued Filter (OR Condition) ---
$query3 = (new AnalyticsQuery('sales', 1000, 2000))
    ->filter('country', ['US', 'Clothing']) // array filter
    ->count('matched_count');
$res3 = $executor->execute($query3);
assertTest(
    count($res3) === 1 && $res3[0]['matched_count'] === 1,
    "Test 12: QueryExecutor evaluates array filter as OR condition via Bitmaps"
);

// --- Test 13: QueryExecutor HyperLogLog Approximate Unique Counting ---
$hllSegment = new ColumnarSegment('traffic', 0, 1000);
$hllSegment->append(new AnalyticsEvent(100, ['device' => 'desktop', 'user' => 'u1'], ['bytes' => 100]));
$hllSegment->append(new AnalyticsEvent(110, ['device' => 'desktop', 'user' => 'u2'], ['bytes' => 200]));
$hllSegment->append(new AnalyticsEvent(120, ['device' => 'desktop', 'user' => 'u1'], ['bytes' => 150])); // repeat u1
$hllSegManager = new SegmentManager();
$hllSegManager->addSegment($hllSegment);
$hllExecutor = new QueryExecutor($hllSegManager);

$queryHll = (new AnalyticsQuery('traffic', 0, 500))
    ->hllUnique('user', 'unique_users')
    ->sum('bytes', 'total_bytes');
$resHll = $hllExecutor->execute($queryHll);
assertTest(
    count($resHll) === 1 &&
    $resHll[0]['unique_users'] === 2 &&
    $resHll[0]['total_bytes'] === 450.0,
    "Test 13: QueryExecutor computes HLL distinct count alongside numeric sums"
);

// --- Test 14: AnalyticsManager End-to-End Ingest, Flush and Query ---
$analytics = new AnalyticsManager();
$analytics->ingest('orders', 1000, ['status' => 'completed', 'payment' => 'card'], ['total' => 200.0]);
$analytics->ingest('orders', 1050, ['status' => 'completed', 'payment' => 'mfs'], ['total' => 300.0]);
$analytics->ingest('orders', 1100, ['status' => 'cancelled', 'payment' => 'card'], ['total' => 100.0]);

$qOrders = $analytics->query('orders')
    ->intervals(0, 2000)
    ->filter('status', 'completed')
    ->sum('total', 'revenue')
    ->count('orders_count');

$ordersResult = $analytics->execute($qOrders);
assertTest(
    count($ordersResult) === 1 &&
    $ordersResult[0]['revenue'] === 500.0 &&
    $ordersResult[0]['orders_count'] === 2,
    "Test 14: AnalyticsManager end-to-end ingest, auto-flush and query execution"
);

// --- Test 15: Global Helpers & Dependency Injection Container Binding ---
$app = new Application(dirname(__DIR__));
$app->singleton(AnalyticsManager::class, fn () => new AnalyticsManager());
$app->singleton('analytics', fn () => $app->make(AnalyticsManager::class));

analytics_ingest('clickstream', ['page' => '/home'], ['duration' => 12]);
analytics_ingest('clickstream', ['page' => '/checkout'], ['duration' => 45]);
$resStream = analytics()->execute(
    analytics_query('clickstream')->sum('duration', 'total_duration')->count('total_clicks')
);

assertTest(
    function_exists('analytics') && function_exists('analytics_ingest') && function_exists('analytics_query') &&
    count($resStream) === 1 &&
    $resStream[0]['total_duration'] === 57.0 &&
    $resStream[0]['total_clicks'] === 2,
    "Test 15: Global helpers analytics(), analytics_ingest(), and analytics_query() function seamlessly"
);

echo "\nPhase 84 Unit Tests Completed: {$passed} Passed, {$failed} Failed.\n";
exit($failed === 0 ? 0 : 1);
