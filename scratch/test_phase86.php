<?php

declare(strict_types=1);

/**
 * Phase 86 Unit Test Suite: Enterprise Full-Stack Micro-Frontend & Dynamic Module Federation Engine (Webpack 5 Architecture)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\MFE\Enums\RenderMode;
use NetFusion\Core\MFE\EventBus\CrossMfeEventBus;
use NetFusion\Core\MFE\Federation\ModuleFederationRegistry;
use NetFusion\Core\MFE\MfeManager;
use NetFusion\Core\MFE\Models\MfeFragment;
use NetFusion\Core\MFE\Models\RemoteContainer;
use NetFusion\Core\MFE\SSR\FragmentStitcher;

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

echo "Starting Phase 86 Tests...\n";

// --- Test 1: RemoteContainer Model & Module Exposition ---
$remote1 = new RemoteContainer(
    name: 'navigation',
    entryUrl: 'https://nav.example.com/remoteEntry.js',
    exposes: [
        './Header' => './src/components/Header.vue',
        './Sidebar' => './src/components/Sidebar.vue',
    ],
    shared: [
        'vue' => ['version' => '3.4.15', 'singleton' => true],
        'pinia' => ['version' => '2.1.7'],
    ]
);

assertTest(
    $remote1->getName() === 'navigation' &&
    $remote1->hasExposed('./Header') &&
    $remote1->getShared()['vue']['singleton'] === true,
    "Test 1: RemoteContainer configures exposed modules and shared dependencies"
);

// --- Test 2: ModuleFederationRegistry Registration & Catalog ---
$registry = new ModuleFederationRegistry();
$registry->registerRemote($remote1);

assertTest(
    $registry->hasRemote('navigation') &&
    $registry->getRemote('navigation')->getName() === 'navigation',
    "Test 2: ModuleFederationRegistry registers and retrieves remote container"
);

// --- Test 3: Shared Scope Version Resolution (Semver Range & Highest Match) ---
$registry->registerShared('react', '18.2.0', 'host', true);
$registry->registerShared('react', '17.0.2', 'legacy_mfe', true);
$registry->registerShared('lodash', '4.17.21', 'host');

$resolvedReact = $registry->resolveShared('react', '^18.0.0');
$resolvedFallback = $registry->resolveShared('react', '^19.0.0'); // None matches, falls back to highest singleton
assertTest(
    $resolvedReact !== null && $resolvedReact['version'] === '18.2.0' &&
    $resolvedFallback !== null && $resolvedFallback['version'] === '18.2.0',
    "Test 3: SharedScope resolves matching semver and falls back to highest singleton"
);

// --- Test 4: Webpack 5 mf-manifest.json Schema Generation ---
$manifest = $registry->generateManifest('shop_host');
assertTest(
    $manifest['name'] === 'shop_host' &&
    $manifest['type'] === 'container' &&
    isset($manifest['remotes']['navigation']) &&
    isset($manifest['shared']['react']),
    "Test 4: Registry exports standard Webpack 5 mf-manifest.json schema"
);

// --- Test 5: MfeFragment Client-Side Placeholder Rendering (CSR Mode) ---
$csrFragment = new MfeFragment('cart', './MiniCart', ['currency' => 'USD', 'items' => 3], RenderMode::CSR, '<p>Loading cart...</p>');
$csrHtml = $csrFragment->renderClientPlaceholder();

assertTest(
    str_contains($csrHtml, 'data-mfe-remote="cart"') &&
    str_contains($csrHtml, 'data-mfe-component="./MiniCart"') &&
    str_contains($csrHtml, 'data-mfe-mode="csr"') &&
    str_contains($csrHtml, 'Loading cart...'),
    "Test 5: MfeFragment generates CSR container placeholder with hydration dataset"
);

// --- Test 6: Edge SSR Fragment Fetching & Custom Mock Dispatch ---
$stitcher = new FragmentStitcher($registry);
$stitcher->setFetcher(function (string $url, array $props, int $timeout) {
    if (str_contains($url, 'component=.%2FHeader')) {
        $user = $props['user'] ?? 'Guest';
        return "<header class=\"mfe-header\"><nav>Welcome, {$user}!</nav></header>";
    }
    return null;
});

$ssrFragment = new MfeFragment('navigation', './Header', ['user' => 'Ashish'], RenderMode::SSR);
$renderedSsr = $stitcher->renderFragment($ssrFragment);

assertTest(
    str_contains($renderedSsr, 'Welcome, Ashish!') &&
    str_starts_with($renderedSsr, '<header'),
    "Test 6: FragmentStitcher server-renders remote SSR fragment using fetcher pipeline"
);

// --- Test 7: Composable Template Shell Stitching with <mfe-fragment ... /> ---
$templateShell = <<<HTML
<!DOCTYPE html>
<html>
<head><title>App Shell</title></head>
<body>
    <mfe-fragment remote="navigation" component="./Header" props='{"user": "Admin"}' />
    <main>
        <h1>Dashboard</h1>
    </main>
</body>
</html>
HTML;

$stitchedHtml = $stitcher->stitch($templateShell);
assertTest(
    str_contains($stitchedHtml, 'Welcome, Admin!') &&
    str_contains($stitchedHtml, '<h1>Dashboard</h1>') &&
    !str_contains($stitchedHtml, '<mfe-fragment'),
    "Test 7: FragmentStitcher compiles page shell replacing custom elements with rendered HTML"
);

// --- Test 8: Circuit Breaker & Fallback Handling on Fragment Timeout / Error ---
$failingFragment = new MfeFragment(
    'nonexistent_remote',
    './Widget',
    [],
    RenderMode::SSR,
    '<div class="fallback-widget">Widget Unavailable</div>'
);
$fallbackResult = $stitcher->renderFragment($failingFragment);

assertTest(
    str_contains($fallbackResult, 'Widget Unavailable'),
    "Test 8: FragmentStitcher invokes fallback HTML when remote container is unavailable"
);

// --- Test 9: Hybrid SSR + Client Hydration Envelope ---
$hybridFragment = new MfeFragment('navigation', './Header', ['user' => 'HybridUser'], RenderMode::HYBRID);
$hybridResult = $stitcher->renderFragment($hybridFragment);

assertTest(
    str_contains($hybridResult, 'data-mfe-mode="hybrid"') &&
    str_contains($hybridResult, 'Welcome, HybridUser!'),
    "Test 9: Hybrid rendering mode encapsulates server HTML within client hydration container"
);

// --- Test 10: Cross-MFE Event Bus Dispatch & Listener Notification ---
$eventBus = new CrossMfeEventBus();
$receivedPayload = null;
$eventBus->on('cart:item_added', function ($payload, $origin) use (&$receivedPayload) {
    $receivedPayload = $payload;
});

$dispatchedCount = $eventBus->dispatch('cart:item_added', ['sku' => 'PROD-101', 'qty' => 2], 'catalog_mfe');
assertTest(
    $dispatchedCount === 1 &&
    $receivedPayload !== null &&
    $receivedPayload['sku'] === 'PROD-101',
    "Test 10: CrossMfeEventBus dispatches and notifies subscribers across isolated boundaries"
);

// --- Test 11: Cross-MFE Shared Global Reactive State Store ---
$stateChangeNotified = false;
$eventBus->on('state:change:active_theme', function ($data) use (&$stateChangeNotified) {
    if ($data['newValue'] === 'dark') {
        $stateChangeNotified = true;
    }
});

$eventBus->setState('active_theme', 'dark');
assertTest(
    $eventBus->getState('active_theme') === 'dark' &&
    $stateChangeNotified,
    "Test 11: CrossMfeEventBus maintains shared state and broadcasts state change events"
);

// --- Test 12: Browser Runtime Bridge Script Generation (window.MfeBus) ---
$bridgeScript = $eventBus->generateBrowserBridge();
assertTest(
    str_contains($bridgeScript, 'window.MfeBus') &&
    str_contains($bridgeScript, 'active_theme') &&
    str_contains($bridgeScript, 'EventTarget'),
    "Test 12: EventBus exports valid browser JavaScript bridge script tag with initial state"
);

// --- Test 13: Master MfeManager Facade Integration ---
$mfe = new MfeManager();
$mfe->registerRemote('checkout', 'https://checkout.example.com', exposes: ['./Payment' => './src/Payment.vue']);
assertTest(
    $mfe->getRegistry()->hasRemote('checkout'),
    "Test 13: MfeManager registers remote containers through unified facade"
);

// --- Test 14: Global Helpers & Dependency Injection Container Binding ---
$app = new Application(dirname(__DIR__));
$app->singleton(MfeManager::class, fn () => new MfeManager());
$app->singleton('mfe', fn () => $app->make(MfeManager::class));

mfe()->registerRemote('auth', 'https://auth.example.com');
$busListenerFired = false;
mfe()->on('auth:login', function () use (&$busListenerFired) {
    $busListenerFired = true;
});
mfe_dispatch('auth:login');

assertTest(
    function_exists('mfe') && function_exists('mfe_fragment') && function_exists('mfe_dispatch') &&
    $busListenerFired &&
    mfe()->getRegistry()->hasRemote('auth'),
    "Test 14: Global helpers mfe(), mfe_fragment(), and mfe_dispatch() function seamlessly"
);

// --- Test 15: Database Migration Up Execution ---
$conn = new \NetFusion\Core\Database\Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$mfeMigration = require __DIR__ . '/../database/migrations/2026_09_28_000069_create_mfe_tables.php';
$mfeMigration->up($conn);

$stmt = $conn->getPdo()->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('mfe_remotes', 'mfe_shared_scopes')");
$createdTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

assertTest(
    count($createdTables) === 2 &&
    in_array('mfe_remotes', $createdTables) &&
    in_array('mfe_shared_scopes', $createdTables),
    "Test 15: Database migration creates mfe_remotes and mfe_shared_scopes tables"
);

echo "\nPhase 86 Unit Tests Completed: {$passed} Passed, {$failed} Failed.\n";
exit($failed === 0 ? 0 : 1);
