<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase31.php
| Module    : Tests
| Purpose   : Automated verification suite for Phase 31 (Feature Flags & A/B Testing).
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

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Console\Kernel as ConsoleKernel;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Features\Drivers\ArrayDriver;
use NetFusion\Core\Features\Drivers\DatabaseDriver;
use NetFusion\Core\Features\FeatureManager;
use NetFusion\Core\Http\Middleware\EnsureFeatureActive;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

$basePath = realpath(__DIR__ . '/..');
$app = new Application($basePath);

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $label, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m: {$label}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL\033[0m: {$label}\n";
        if ($details !== '') {
            echo "         Detail: {$details}\n";
        }
    }
}

echo "\n============================================================\n";
echo "  NetFusion Verification Suite — Phase 31: Feature Flags & A/B\n";
echo "============================================================\n";

// =========================================================================
//  SECTION 1: In-Memory ArrayDriver & Basic Operations
// =========================================================================
echo "\n\033[33m--- [Section 1] ArrayDriver & Basic Flags ---\033[0m\n";

$arrayDriver = new ArrayDriver();
$manager = new FeatureManager($app, $arrayDriver);

assertTest($manager->active('beta_dark_mode') === false, 'Non-existent feature is inactive by default');
$manager->activate('beta_dark_mode');
assertTest($manager->active('beta_dark_mode') === true, 'activate() makes feature globally active');
assertTest($manager->inactive('beta_dark_mode') === false, 'inactive() returns false when active');

$manager->deactivate('beta_dark_mode');
assertTest($manager->active('beta_dark_mode') === false, 'deactivate() turns feature off');
assertTest($manager->inactive('beta_dark_mode') === true, 'inactive() returns true when inactive');

// Runtime definition closure
$manager->define('admin_dashboard', function ($user) {
    return is_array($user) && ($user['role'] ?? '') === 'admin';
});

assertTest($manager->active('admin_dashboard', ['role' => 'user']) === false, 'Closure definition rejects standard user');
assertTest($manager->active('admin_dashboard', ['role' => 'admin']) === true, 'Closure definition approves admin role');

// =========================================================================
//  SECTION 2: Canary Percentage Rollout (Consistent Hashing)
// =========================================================================
echo "\n\033[33m--- [Section 2] Canary Percentage Rollout ---\033[0m\n";

$manager->percentage('new_checkout_flow', 30); // 30% canary rollout

// 1. Consistency / Idempotency check: Same user must ALWAYS get the same result
$userA = 10052;
$res1 = $manager->active('new_checkout_flow', $userA);
$res2 = $manager->active('new_checkout_flow', $userA);
$res3 = $manager->active('new_checkout_flow', $userA);
assertTest($res1 === $res2 && $res2 === $res3, 'Consistent hashing ensures deterministic flag result for User A across calls');

// 2. Population distribution check across 200 users
$activeCount = 0;
for ($uid = 1; $uid <= 200; $uid++) {
    if ($manager->active('new_checkout_flow', $uid)) {
        $activeCount++;
    }
}
$observedPercentage = ($activeCount / 200) * 100;
assertTest($observedPercentage >= 20 && $observedPercentage <= 40, "30% rollout distributes evenly (observed: {$observedPercentage}%)");

// 0% rollout disables for everyone
$manager->percentage('disabled_canary', 0);
assertTest($manager->active('disabled_canary', 1) === false, '0% percentage rollout is inactive');
assertTest($manager->active('disabled_canary', 9999) === false, '0% percentage rollout is inactive for high IDs');

// 100% rollout enables for everyone
$manager->percentage('full_canary', 100);
assertTest($manager->active('full_canary', 1) === true, '100% percentage rollout is active for ID 1');
assertTest($manager->active('full_canary', 9999) === true, '100% percentage rollout is active for ID 9999');

// =========================================================================
//  SECTION 3: User & Scope Targeting
// =========================================================================
echo "\n\033[33m--- [Section 3] User & Scope Targeting ---\033[0m\n";

$manager->activateFor('vip_rewards', [101, 102, 105]);
assertTest($manager->active('vip_rewards', 101) === true, 'VIP rewards active for targeted User 101');
assertTest($manager->active('vip_rewards', 102) === true, 'VIP rewards active for targeted User 102');
assertTest($manager->active('vip_rewards', 103) === false, 'VIP rewards inactive for non-targeted User 103');

// Deactivate for specific user
$manager->deactivateFor('vip_rewards', 102);
assertTest($manager->active('vip_rewards', 101) === true, 'User 101 remains active after 102 deactivated');
assertTest($manager->active('vip_rewards', 102) === false, 'User 102 is successfully deactivated');

// =========================================================================
//  SECTION 4: Database Driver & Migration Persistence
// =========================================================================
echo "\n\033[33m--- [Section 4] Database Driver & Migration ---\033[0m\n";

$testDbPath = __DIR__ . '/test_phase31.sqlite';
if (file_exists($testDbPath)) {
    @unlink($testDbPath);
}
touch($testDbPath);

$config = $app->make(Config::class);
$config->set('database.default', 'sqlite');
$config->set('database.connections.sqlite.database', $testDbPath);

/** @var DatabaseManager $dbManager */
$dbManager = $app->make(DatabaseManager::class);
$conn = $dbManager->connection('sqlite');

// Run feature flags migration
$migration = require __DIR__ . '/../database/migrations/2026_09_18_000016_create_feature_flags_table.php';
$migration->up($conn);
assertTest(true, 'Database migrated with feature_flags table');

$dbDriver = new DatabaseDriver($dbManager);
$dbFeatureManager = new FeatureManager($app, $dbDriver);

$dbFeatureManager->activate('cloud_sync');
assertTest($dbFeatureManager->active('cloud_sync') === true, 'Feature activated via DatabaseDriver');

// Verify DB table contains record
$row = $conn->selectOne("SELECT * FROM feature_flags WHERE name = :name", [':name' => 'cloud_sync']);
assertTest($row !== null && $row['status'] === 'active', 'Row persisted in feature_flags SQLite table');

// Set percentage in DB
$dbFeatureManager->percentage('ai_assistant', 50);
$aiRow = $conn->selectOne("SELECT * FROM feature_flags WHERE name = :name", [':name' => 'ai_assistant']);
assertTest((int) $aiRow['percentage'] === 50, 'Percentage persisted as integer in database');

// List all from DB driver
$allDbFeatures = $dbFeatureManager->all();
assertTest(isset($allDbFeatures['cloud_sync'], $allDbFeatures['ai_assistant']), 'all() retrieves all database records');

// =========================================================================
//  SECTION 5: A/B Testing & Experiment Variants
// =========================================================================
echo "\n\033[33m--- [Section 5] A/B Testing & Experiment Variants ---\033[0m\n";

// 50/50 Split experiment
$variant1 = $manager->variant('pricing_table_v2', 5001, ['control' => 50, 'treatment' => 50]);
assertTest(in_array($variant1, ['control', 'treatment'], true), 'Assigned variant is either control or treatment');

// Deterministic variant consistency
$variant1Repeat = $manager->variant('pricing_table_v2', 5001, ['control' => 50, 'treatment' => 50]);
assertTest($variant1 === $variant1Repeat, 'A/B variant assignment is 100% consistent for user 5001 across calls');

// Multi-variant experiment with weights [A: 20%, B: 30%, C: 50%]
$variantsDistribution = ['A' => 0, 'B' => 0, 'C' => 0];
for ($uid = 1; $uid <= 300; $uid++) {
    $v = $manager->variant('header_banner', $uid, ['A' => 20, 'B' => 30, 'C' => 50]);
    $variantsDistribution[$v]++;
}
assertTest($variantsDistribution['A'] > 0, 'Variant A received user allocation');
assertTest($variantsDistribution['B'] > 0, 'Variant B received user allocation');
assertTest($variantsDistribution['C'] > $variantsDistribution['A'], 'Variant C (50% weight) received higher allocation than A (20%)');

// =========================================================================
//  SECTION 6: EnsureFeatureActive Middleware
// =========================================================================
echo "\n\033[33m--- [Section 6] EnsureFeatureActive Middleware ---\033[0m\n";

$manager->activate('new_profile_page');
$manager->deactivate('legacy_export');

$middlewareActive = new EnsureFeatureActive($manager, 'new_profile_page');
$middlewareInactive = new EnsureFeatureActive($manager, 'legacy_export');

$req = Request::create(uri: '/profile/v2', method: 'GET');
$req->setAttribute('user_id', 42);

$nextHandler = function (Request $r) {
    return Response::json(['status' => 'success', 'data' => 'allowed']);
};

// 1. Active feature route passes
$resActive = $middlewareActive->handle($req, $nextHandler);
assertTest($resActive->getStatusCode() === 200, 'Middleware permits request when feature is active (200 OK)');

// 2. Inactive feature route returns 404
$resInactive = $middlewareInactive->handle($req, $nextHandler);
assertTest($resInactive->getStatusCode() === 404, 'Middleware blocks request with 404 when feature is inactive');
$inactiveBody = json_decode($resInactive->getContent(), true);
assertTest(str_contains($inactiveBody['message'], 'legacy_export'), 'Error message identifies the disabled feature');

// =========================================================================
//  SECTION 7: Global Helpers (feature, feature_variant)
// =========================================================================
echo "\n\033[33m--- [Section 7] Global Helpers ---\033[0m\n";

assertTest(function_exists('feature'), 'Global helper feature() exists');
assertTest(function_exists('feature_variant'), 'Global helper feature_variant() exists');
assertTest(feature() instanceof FeatureManager, 'feature() resolves FeatureManager instance');

// Using app container's feature manager
$appFeatures = $app->make('features');
$appFeatures->activate('instant_search');

assertTest(feature('instant_search') === true, 'feature(name) evaluates active flag');
assertTest(feature('non_existent_feature') === false, 'feature(non_existent) returns false');
assertTest(feature('non_existent_feature', null, true) === true, 'feature() supports default fallback');

$assignedVariant = feature_variant('button_color', 99, ['blue' => 50, 'green' => 50]);
assertTest(in_array($assignedVariant, ['blue', 'green'], true), 'feature_variant() selects valid variant');

// =========================================================================
//  SECTION 8: CLI Commands (feature:enable, feature:list, feature:disable)
// =========================================================================
echo "\n\033[33m--- [Section 8] CLI Commands ---\033[0m\n";

/** @var ConsoleKernel $console */
$console = $app->make(ConsoleKernel::class);

// 1. feature:enable globally
ob_start();
$codeEnable = $console->handle(['netfusion', 'feature:enable', 'realtime_notifications']);
$outEnable = ob_get_clean();
assertTest($codeEnable === 0, 'CLI feature:enable exits with code 0');
assertTest(str_contains($outEnable, 'enabled globally'), 'CLI feature:enable reports global activation');

// 2. feature:enable with percentage
ob_start();
$codeEnablePct = $console->handle(['netfusion', 'feature:enable', 'beta_editor', '--percentage=40']);
$outEnablePct = ob_get_clean();
assertTest($codeEnablePct === 0, 'CLI feature:enable --percentage exits with code 0');
assertTest(str_contains($outEnablePct, '40%'), 'CLI feature:enable reports 40% canary rollout');

// 3. feature:list
ob_start();
$codeList = $console->handle(['netfusion', 'feature:list']);
$outList = ob_get_clean();
assertTest($codeList === 0, 'CLI feature:list exits with code 0');
assertTest(str_contains($outList, 'realtime_notifications'), 'CLI feature:list displays active feature');
assertTest(str_contains($outList, 'beta_editor'), 'CLI feature:list displays canary feature');

// 4. feature:disable
ob_start();
$codeDisable = $console->handle(['netfusion', 'feature:disable', 'realtime_notifications']);
$outDisable = ob_get_clean();
assertTest($codeDisable === 0, 'CLI feature:disable exits with code 0');
assertTest(str_contains($outDisable, 'deactivated globally'), 'CLI feature:disable reports deactivation');

// Clean up test sqlite
if (file_exists($testDbPath)) {
    @unlink($testDbPath);
}

// =========================================================================
//  SUMMARY
// =========================================================================
echo "\n============================================================\n";
echo "  Verification Results: Phase 31\n";
echo "  Total:  " . ($passed + $failed) . "\n";
echo "  Passed: \033[32m{$passed}\033[0m\n";
echo "  Failed: \033[31m{$failed}\033[0m\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
