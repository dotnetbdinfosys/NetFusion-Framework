<?php

/**
 * NetFusion Phase 51 Test Suite:
 * Enterprise Time-Series Database Engine, Anomaly Detection & Predictive Auto-Scaling
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Data\TimeSeries\Aggregator;
use NetFusion\Core\Data\TimeSeries\Anomaly\AnomalyDetector;
use NetFusion\Core\Data\TimeSeries\Anomaly\AnomalyResult;
use NetFusion\Core\Data\TimeSeries\Anomaly\EwmaDetector;
use NetFusion\Core\Data\TimeSeries\Anomaly\IqrDetector;
use NetFusion\Core\Data\TimeSeries\Anomaly\ZScoreDetector;
use NetFusion\Core\Data\TimeSeries\DataPoint;
use NetFusion\Core\Data\TimeSeries\Scaling\AutoScaler;
use NetFusion\Core\Data\TimeSeries\Scaling\LinearRegressionPredictor;
use NetFusion\Core\Data\TimeSeries\Scaling\ScalingDecision;
use NetFusion\Core\Data\TimeSeries\Scaling\ScalingPolicy;
use NetFusion\Core\Data\TimeSeries\Store\ArrayTimeSeriesStore;
use NetFusion\Core\Data\TimeSeries\Store\DatabaseTimeSeriesStore;
use NetFusion\Core\Data\TimeSeries\TimeSeries;
use NetFusion\Core\Data\TimeSeries\TimeSeriesManager;

echo "\n=======================================================\n";
echo "   NetFusion Phase 51: TSDB, Anomaly & Scaling Suite   \n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "✔ [PASS] {$desc}\n";
        $passed++;
    } else {
        echo "✖ [FAIL] {$desc}\n";
        $failed++;
    }
}

// -----------------------------------------------------------------------------
// 1. DataPoint Value Object
// -----------------------------------------------------------------------------
echo "--- 1. DataPoint Value Object ---\n";

$dp = DataPoint::create('cpu_usage', 78.5, 1700000000.0, ['host' => 'srv-01', 'region' => 'us-east']);
assertTest('DataPoint: Correctly sets metric, value, timestamp, and tags',
    $dp->metric === 'cpu_usage' &&
    $dp->value === 78.5 &&
    $dp->timestamp === 1700000000.0 &&
    $dp->tags['host'] === 'srv-01'
);

// -----------------------------------------------------------------------------
// 2. Mathematical Aggregations & Statistical Calculations
// -----------------------------------------------------------------------------
echo "\n--- 2. Aggregator Functions & Statistical Math ---\n";

$values = [10.0, 20.0, 30.0, 40.0, 50.0];
assertTest('Aggregator: avg() calculates correct mean (30.0)', Aggregator::avg($values) === 30.0);
assertTest('Aggregator: sum() calculates correct sum (150.0)', Aggregator::sum($values) === 150.0);
assertTest('Aggregator: min() and max() calculate bounds (10.0 / 50.0)', Aggregator::min($values) === 10.0 && Aggregator::max($values) === 50.0);
assertTest('Aggregator: stddev() calculates sample standard deviation', round(Aggregator::stddev($values), 2) === 15.81);
assertTest('Aggregator: percentile() calculates p50, p90, p99',
    Aggregator::percentile($values, 50.0) === 30.0 &&
    Aggregator::percentile($values, 90.0) === 46.0 &&
    Aggregator::percentile($values, 99.0) === 49.6
);

// -----------------------------------------------------------------------------
// 3. TimeSeries Collection, Slicing & Windowing
// -----------------------------------------------------------------------------
echo "\n--- 3. TimeSeries Collection & Windowing ---\n";

$now = 1700000000.0;
$points = [
    new DataPoint('http_requests', 10.0, $now),
    new DataPoint('http_requests', 20.0, $now + 10),
    new DataPoint('http_requests', 30.0, $now + 20),
    new DataPoint('http_requests', 40.0, $now + 70),
    new DataPoint('http_requests', 50.0, $now + 80),
];

$series = new TimeSeries('http_requests', $points);
assertTest('TimeSeries: Aggregates collection values accurately', $series->aggregate('avg') === 30.0);
assertTest('TimeSeries: slice() filters points within timestamp range', $series->slice($now, $now + 30)->count() === 3);

$windowed = $series->window(60, 'avg');
assertTest('TimeSeries: window() buckets points into discrete 60s intervals',
    $windowed->count() === 2 &&
    $windowed->getPoints()[0]->value === 20.0 && // (10+20+30)/3
    $windowed->getPoints()[1]->value === 45.0    // (40+50)/2
);

// -----------------------------------------------------------------------------
// 4. In-Memory Store & Database TSDB Persistence
// -----------------------------------------------------------------------------
echo "\n--- 4. TSDB Storage & Database Migration ---\n";

$arrStore = new ArrayTimeSeriesStore();
$arrStore->insert(...$points);
assertTest('ArrayTimeSeriesStore: Ingests and queries by metric and tag filter',
    $arrStore->count('http_requests') === 5 &&
    $arrStore->query('http_requests', $now, $now + 100)->count() === 5
);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_20_000031_create_timeseries_metrics_tables.php';
$migration->up($pdo);

$dbStore = new DatabaseTimeSeriesStore($pdo);
$dbStore->insert(
    new DataPoint('latency', 45.2, $now, ['env' => 'prod']),
    new DataPoint('latency', 52.8, $now + 10, ['env' => 'prod']),
    new DataPoint('latency', 48.0, $now + 20, ['env' => 'staging'])
);

assertTest('DatabaseTimeSeriesStore: Ingests and filters by JSON tags in SQLite',
    $dbStore->count('latency') === 3 &&
    $dbStore->query('latency', $now, $now + 50, ['env' => 'prod'])->count() === 2
);

$rollupsCreated = $dbStore->downsample('latency', 60, 'avg');
assertTest('DatabaseTimeSeriesStore: Downsamples raw metrics into rollup table', $rollupsCreated === 1);

// Prune raw data
$pruned = $dbStore->prune($now + 15);
assertTest('DatabaseTimeSeriesStore: Prunes expired raw points', $pruned === 2 && $dbStore->count('latency') === 1);

// Migration Down
$migration->down($pdo);
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_timeseries_raw'");
$dropped = $stmt && $stmt->fetchColumn() === false;
assertTest('Database Migration: Dropped TSDB tables cleanly on down()', $dropped);

// -----------------------------------------------------------------------------
// 5. Statistical Anomaly Detection (Z-Score, IQR, EWMA, Ensemble)
// -----------------------------------------------------------------------------
echo "\n--- 5. Anomaly Detection Engine ---\n";

// Normal baseline around 100 with one spike at 350
$normalWithSpike = [];
for ($i = 0; $i < 20; $i++) {
    $normalWithSpike[] = new DataPoint('traffic', 100.0 + ($i % 3) * 2.0, $now + ($i * 10));
}
$normalWithSpike[] = new DataPoint('traffic', 350.0, $now + 200); // Extreme outlier

$anomalySeries = new TimeSeries('traffic', $normalWithSpike);

$zscoreDetector = new ZScoreDetector(2.5);
$zRes = $zscoreDetector->detect($anomalySeries);
assertTest('ZScoreDetector: Flags extreme spike as anomaly (z > 2.5)',
    $zRes->isAnomaly &&
    $zRes->actualValue === 350.0 &&
    count($zRes->anomalies) >= 1
);

$iqrDetector = new IqrDetector(1.5);
$iqrRes = $iqrDetector->detect($anomalySeries);
assertTest('IqrDetector: Flags quartile outlier accurately',
    $iqrRes->isAnomaly &&
    $iqrRes->actualValue === 350.0
);

$ewmaDetector = new EwmaDetector(0.3, 2.5);
$ewmaRes = $ewmaDetector->detect($anomalySeries);
assertTest('EwmaDetector: Flags divergence from exponentially weighted baseline',
    $ewmaRes->isAnomaly &&
    $ewmaRes->actualValue === 350.0
);

$anomalyFacade = new AnomalyDetector();
$ensembleRes = $anomalyFacade->ensemble($anomalySeries);
assertTest('AnomalyDetector: Ensemble majority voting flags anomaly across algorithms',
    $ensembleRes->isAnomaly &&
    $ensembleRes->method === 'ensemble'
);

// -----------------------------------------------------------------------------
// 6. Linear Regression Trend Extrapolation & Forecasting
// -----------------------------------------------------------------------------
echo "\n--- 6. Trend Forecasting & Predictive Linear Regression ---\n";

// Linear upward trend: y = 2x + 10
$trendPoints = [];
for ($i = 0; $i < 10; $i++) {
    $trendPoints[] = new DataPoint('queue_size', 10.0 + (2.0 * $i * 10), $now + ($i * 10));
}
$trendSeries = new TimeSeries('queue_size', $trendPoints);

$forecast = LinearRegressionPredictor::forecast($trendSeries, 30.0);
assertTest('LinearRegressionPredictor: Accurately extrapolates future workload with high R²',
    $forecast['slope'] > 0.19 &&
    $forecast['r_squared'] >= 0.99 &&
    $forecast['predicted_value'] >= 240.0
);

// -----------------------------------------------------------------------------
// 7. Auto-Scaling Engine & Policy Evaluation
// -----------------------------------------------------------------------------
echo "\n--- 7. Predictive Auto-Scaling Engine ---\n";

$policy = new ScalingPolicy(
    name: 'web_workers',
    metric: 'queue_size',
    targetValue: 100.0,
    minReplicas: 2,
    maxReplicas: 10,
    scaleUpThreshold: 1.2,
    scaleDownThreshold: 0.7,
    cooldownSeconds: 60,
    forecastHorizonSeconds: 30
);

$autoScaler = new AutoScaler();
$autoScaler->addPolicy($policy);

// Current queue is high (200), predicted (250) -> Scale up from 2 to 5 replicas
$decision = $autoScaler->evaluate('web_workers', $trendSeries, 2);
assertTest('AutoScaler: Computes SCALE_UP action based on forecasted workload',
    $decision->action === ScalingDecision::ACTION_SCALE_UP &&
    $decision->desiredReplicas > 2 &&
    $decision->currentReplicas === 2
);

// Immediate second evaluation should be in COOLDOWN
$cooldownDecision = $autoScaler->evaluate('web_workers', $trendSeries, $decision->desiredReplicas);
assertTest('AutoScaler: Enforces cooldown guard against flapping',
    $cooldownDecision->action === ScalingDecision::ACTION_COOLDOWN
);

// -----------------------------------------------------------------------------
// 8. TimeSeriesManager Facade & Global Helpers
// -----------------------------------------------------------------------------
echo "\n--- 8. TimeSeriesManager & Global Helpers ---\n";

$tsdbMgr = new TimeSeriesManager(new ArrayTimeSeriesStore(), $anomalyFacade, $autoScaler);

$app = new Application(dirname(__DIR__));
$app->instance(TimeSeriesManager::class, $tsdbMgr);
$app->instance('tsdb', $tsdbMgr);

assertTest('Global Helper: tsdb() returns TimeSeriesManager instance', tsdb() instanceof TimeSeriesManager);

tsdb_write('api_latency', 120.5, ['endpoint' => '/checkout']);
$queried = tsdb('api_latency');

assertTest('Global Helper: tsdb_write() writes and tsdb($metric) queries series',
    $queried instanceof TimeSeries &&
    $queried->count() === 1 &&
    $queried->latest()?->value === 120.5
);

$anomalyQuick = anomaly_detect($anomalySeries, 'zscore');
assertTest('Global Helper: anomaly_detect() evaluates time-series anomaly', $anomalyQuick instanceof AnomalyResult && $anomalyQuick->isAnomaly);

echo "\n=======================================================\n";
echo "   Phase 51 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
