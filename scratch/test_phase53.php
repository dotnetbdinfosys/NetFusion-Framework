<?php

/**
 * NetFusion Phase 53 Test Suite: Enterprise Global Feature Flagging, Multivariate Canary Rollouts & Experimentation Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Experimentation\EvaluationContext;
use NetFusion\Core\Experimentation\Segment\Rule;
use NetFusion\Core\Experimentation\Segment\Segment;
use NetFusion\Core\Experimentation\Canary\CanaryRollout;
use NetFusion\Core\Experimentation\Variant;
use NetFusion\Core\Experimentation\Experiment;
use NetFusion\Core\Experimentation\Exposure;
use NetFusion\Core\Experimentation\Conversion;
use NetFusion\Core\Experimentation\Statistics\StatisticalEngine;
use NetFusion\Core\Experimentation\Store\ArrayExperimentStore;
use NetFusion\Core\Experimentation\Store\DatabaseExperimentStore;
use NetFusion\Core\Experimentation\ExperimentManager;

echo "\n=======================================================\n";
echo "   NetFusion Phase 53: Multivariate Experiments & Canary \n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "✔ [PASS] {$description}\n";
    } else {
        $failed++;
        echo "✖ [FAIL] {$description}\n";
    }
}

// -----------------------------------------------------------------------------
// 1. EvaluationContext & Attribute Extraction
// -----------------------------------------------------------------------------
echo "--- 1. EvaluationContext & Attribute Resolution ---\n";

$ctx1 = EvaluationContext::from(['user_id' => 'u_101', 'plan' => 'enterprise', 'geo' => ['country' => 'BD']]);
assertTest('EvaluationContext: Resolves direct and nested dot-notated attributes',
    $ctx1->getIdentifier() === 'u_101' &&
    $ctx1->get('plan') === 'enterprise' &&
    $ctx1->get('geo.country') === 'BD' &&
    $ctx1->get('non_existent', 'def') === 'def'
);

$anonCtx = EvaluationContext::from(['anonymous_id' => 'anon_999']);
assertTest('EvaluationContext: Fallbacks to anonymousId when userId is missing',
    $anonCtx->getIdentifier() === 'anon_999'
);

// -----------------------------------------------------------------------------
// 2. Segmentation & Multi-Operator Rules Engine
// -----------------------------------------------------------------------------
echo "\n--- 2. Segment & Multi-Operator Rules ---\n";

$rule1 = new Rule('plan', 'in', ['pro', 'enterprise']);
$rule2 = new Rule('version', 'semver_gte', '2.5.0');
$rule3 = new Rule('email', 'ends_with', '@company.com');

$segment = new Segment('enterprise_v2', [$rule1, $rule2, $rule3], 'all');

$matchingUser = EvaluationContext::from([
    'user_id' => 'emp_1',
    'plan' => 'enterprise',
    'version' => '3.0.1',
    'email' => 'alice@company.com'
]);

$failingUser = EvaluationContext::from([
    'user_id' => 'emp_2',
    'plan' => 'free',
    'version' => '3.0.1',
    'email' => 'bob@company.com'
]);

assertTest('Segment: Matches all composite rules (IN, SemVer >=, EndsWith)', $segment->matches($matchingUser));
assertTest('Segment: Rejects context failing one rule in ALL matchType', !$segment->matches($failingUser));

$orSegment = new Segment('or_group', [$rule1, $rule2], 'any');
assertTest('Segment: Matches when at least one rule matches in ANY matchType', $orSegment->matches($failingUser));

// -----------------------------------------------------------------------------
// 3. Canary Progressive Rollout & Error Killswitch
// -----------------------------------------------------------------------------
echo "\n--- 3. Canary Progressive Rollout & Error Killswitch ---\n";

$now = 1700000000.0;
$stages = [
    ['after_timestamp' => $now + 100, 'percentage' => 10],
    ['after_timestamp' => $now + 500, 'percentage' => 50],
    ['after_timestamp' => $now + 1000, 'percentage' => 100],
];

$canary = new CanaryRollout(
    flagKey: 'payment_v2',
    percentage: 5,
    stages: $stages,
    errorThresholdPercent: 10.0
);

assertTest('CanaryRollout: Returns initial 5% before stage timestamp', $canary->getEffectivePercentage($now) === 5);
assertTest('CanaryRollout: Dynamically ramps to 50% after stage timestamp', $canary->getEffectivePercentage($now + 600) === 50);

// Consistent hashing distribution
$canaryCount = 0;
for ($i = 0; $i < 1000; $i++) {
    $c = new EvaluationContext(userId: "user_{$i}");
    if ($canary->evaluate($c, $now + 600)) { // 50% rollout
        $canaryCount++;
    }
}
assertTest('CanaryRollout: Consistently hashes subjects close to 50% bucket (450-550)', $canaryCount >= 450 && $canaryCount <= 550);

// Trigger killswitch on high error rate
$triggered = $canary->recordMetrics(100, 15); // 15% error rate > 10% threshold
assertTest('CanaryRollout: Automatic error rate killswitch triggers', $triggered && $canary->killSwitchTriggered);
assertTest('CanaryRollout: Shuts down all traffic to 0% once killed', !$canary->evaluate(new EvaluationContext('user_1'), $now + 600));

$canary->resetKillSwitch();
assertTest('CanaryRollout: Resets killswitch cleanly', !$canary->killSwitchTriggered);

// -----------------------------------------------------------------------------
// 4. Multivariate A/B/n Testing & Variant Selection
// -----------------------------------------------------------------------------
echo "\n--- 4. Multivariate Experiment & Deterministic Hashing ---\n";

$variants = [
    new Variant('control', 50, ['button_color' => 'gray']),
    new Variant('blue_theme', 25, ['button_color' => 'blue']),
    new Variant('green_theme', 25, ['button_color' => 'green']),
];

$experiment = new Experiment(
    key: 'cta_button_exp',
    name: 'CTA Button Color Optimization',
    status: Experiment::STATUS_RUNNING,
    variants: $variants,
    goals: ['checkout_complete', 'click_cta'],
    trafficAllocation: 100
);

$userA = new EvaluationContext('usr_alpha');
$userB = new EvaluationContext('usr_beta');

$varA1 = $experiment->assignVariant($userA);
$varA2 = $experiment->assignVariant($userA);
assertTest('Experiment: Deterministic assignment assigns identical variant for same subject',
    $varA1 !== null && $varA1->name === $varA2->name
);

// Concluded with winner override
$experiment->status = Experiment::STATUS_CONCLUDED;
$experiment->winnerVariant = 'green_theme';
assertTest('Experiment: Concluded experiment always assigns declared winner variant',
    $experiment->assignVariant($userA)?->name === 'green_theme' &&
    $experiment->assignVariant($userB)?->name === 'green_theme'
);

// -----------------------------------------------------------------------------
// 5. Statistical Hypothesis Testing Engine (Z-Test, P-Value & Uplift)
// -----------------------------------------------------------------------------
echo "\n--- 5. Statistical Hypothesis Testing Engine ---\n";

// Simulated experiment:
// Control: 1,000 users, 100 conversions (10.0%)
// Treatment A: 1,000 users, 150 conversions (15.0%) -> Significant Uplift (+50%)
// Treatment B: 1,000 users, 102 conversions (10.2%) -> Not Significant (+2%)
$exposures = ['control' => 1000, 'treatment_a' => 1000, 'treatment_b' => 1000];
$conversions = ['control' => 100, 'treatment_a' => 150, 'treatment_b' => 102];
$revenue = ['control' => 10000.0, 'treatment_a' => 18000.0, 'treatment_b' => 10300.0];

$analysis = StatisticalEngine::analyze($exposures, $conversions, $revenue, 'control', 0.05);

assertTest('StatisticalEngine: Calculates accurate baseline conversion rates and ARPU',
    $analysis['variants']['control']['conversion_rate_pct'] === 10.0 &&
    $analysis['variants']['treatment_a']['conversion_rate_pct'] === 15.0 &&
    $analysis['variants']['treatment_a']['arpu'] === 18.0
);

$compA = $analysis['comparisons']['treatment_a'];
assertTest('StatisticalEngine: Detects +50% relative uplift on treatment A', $compA['relative_uplift_pct'] === 50.0);
assertTest('StatisticalEngine: Calculates Z-score > 3.0 and p-value < 0.001 with 95%+ confidence',
    $compA['z_score'] > 3.0 &&
    $compA['p_value'] < 0.001 &&
    $compA['confidence_pct'] >= 99.0 &&
    $compA['is_significant'] === true
);

$compB = $analysis['comparisons']['treatment_b'];
assertTest('StatisticalEngine: Flags low uplift variant as not statistically significant',
    $compB['is_significant'] === false &&
    $compB['p_value'] > 0.05
);

assertTest('StatisticalEngine: Identifies treatment_a as the statistical winner',
    $analysis['winner'] === 'treatment_a'
);

// -----------------------------------------------------------------------------
// 6. Database Storage & Migration
// -----------------------------------------------------------------------------
echo "\n--- 6. Database Storage & Migration ---\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_20_000033_create_experimentation_tables.php';
$migration->up($pdo);

$dbStore = new DatabaseExperimentStore($pdo);

// Save and retrieve experiment
$testExp = new Experiment('header_v3', 'Header Design V3', Experiment::STATUS_RUNNING, $variants, null, ['sign_up']);
$dbStore->saveExperiment($testExp);
$loadedExp = $dbStore->getExperiment('header_v3');
assertTest('DatabaseExperimentStore: Persists and retrieves experiment with variants',
    $loadedExp !== null &&
    $loadedExp->key === 'header_v3' &&
    count($loadedExp->variants) === 3
);

// Record exposures and conversions
$dbStore->recordExposure(new Exposure('exp_1', 'header_v3', 'blue_theme', 'user_1', ['browser' => 'Chrome'], microtime(true)));
$dbStore->recordExposure(new Exposure('exp_2', 'header_v3', 'blue_theme', 'user_2', ['browser' => 'Firefox'], microtime(true)));
$dbStore->recordConversion(new Conversion('cnv_1', 'header_v3', 'blue_theme', 'user_1', 'sign_up', 25.0, [], microtime(true)));

$expCount = $dbStore->getExposures('header_v3');
$cnvCount = $dbStore->getConversions('header_v3', 'sign_up');
$rev = $dbStore->getRevenue('header_v3');

assertTest('DatabaseExperimentStore: Aggregates distinct exposures, conversions and revenue',
    ($expCount['blue_theme'] ?? 0) === 2 &&
    ($cnvCount['blue_theme'] ?? 0) === 1 &&
    ($rev['blue_theme'] ?? 0.0) === 25.0
);

// Down Migration test
$migration->down($pdo);
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_experiments'");
$dropped = $stmt && $stmt->fetchColumn() === false;
assertTest('Database Migration: Down() cleanly drops experimentation tables', $dropped);

// -----------------------------------------------------------------------------
// 7. ExperimentManager Facade & DI Container
// -----------------------------------------------------------------------------
echo "\n--- 7. ExperimentManager Facade & Global Helpers ---\n";

$memStore = new ArrayExperimentStore();
$manager = new ExperimentManager($memStore);

$pricingExp = new Experiment(
    key: 'pricing_tier_2026',
    name: 'Pricing Tier 2026',
    status: Experiment::STATUS_RUNNING,
    variants: [
        new Variant('control', 50, ['discount' => 0]),
        new Variant('promo_20', 50, ['discount' => 20]),
    ],
    goals: ['purchase']
);
$manager->define($pricingExp);

$variantName = $manager->variant('pricing_tier_2026', 'customer_101');
$payload = $manager->payload('pricing_tier_2026', 'customer_101');

assertTest('ExperimentManager: variant() and payload() retrieve correct variant configuration',
    in_array($variantName, ['control', 'promo_20'], true) &&
    isset($payload['discount'])
);

// Track goal
$manager->trackGoal('purchase', 100.0, 'customer_101');
$results = $manager->getResults('pricing_tier_2026');
assertTest('ExperimentManager: trackGoal() records conversion against active variant',
    $results['total_exposures'] >= 1 &&
    $results['total_conversions'] >= 1
);

// App DI & Global Helpers
$app = new Application(dirname(__DIR__));
$app->instance(ExperimentManager::class, $manager);
$app->instance('experiment', $manager);

assertTest('Global Helper: experiment() returns ExperimentManager instance', experiment() instanceof ExperimentManager);
assertTest('Global Helper: ab_test() returns assigned variant', in_array(ab_test('pricing_tier_2026', 'customer_101'), ['control', 'promo_20'], true));

track_conversion('purchase', 50.0, 'customer_202', 'pricing_tier_2026');
assertTest('Global Helper: track_conversion() executes without error', true);

echo "\n=======================================================\n";
echo "   Phase 53 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
