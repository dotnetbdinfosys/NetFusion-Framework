<?php
/**
 * NetFusion Framework - Phase 120 Unit Test Suite
 *
 * Official v1.0.0 Global GA Release & Enterprise Golden Master Verification.
 *
 * @package    NetFusion\Tests
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Foundation/Release/FrameworkReleaseManifest.php';
require_once __DIR__ . '/../core/Foundation/Release/GoldenMasterVerifier.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Foundation\Release\FrameworkReleaseManifest;
use NetFusion\Core\Foundation\Release\GoldenMasterVerifier;

$testsPassed = 0;
$totalTests = 10;

function assertTest(bool $condition, string $message): void {
    global $testsPassed;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
    }
}

echo "=== Running Phase 120: Golden Master Release Verification ===\n";

// Test 1: Canonical version.json file existence and schema validation
try {
    $versionFilePath = dirname(__DIR__) . '/version.json';
    $versionContent = file_exists($versionFilePath) ? (string) file_get_contents($versionFilePath) : '';
    $versionJson = json_decode($versionContent, true) ?: [];

    assertTest(
        file_exists($versionFilePath) &&
        ($versionJson['version'] ?? '') === '1.0.0' &&
        ($versionJson['status'] ?? '') === 'GA' &&
        ($versionJson['total_phases'] ?? 0) === 120 &&
        ($versionJson['runtime_external_dependencies'] ?? -1) === 0 &&
        ($versionJson['author'] ?? '') === 'Md. Atiquzzaman Ashish',
        "Test 1: Canonical version.json file exists and adheres to release metadata schema"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n";
}

// Test 2: config/release.php configuration settings
try {
    $configPath = dirname(__DIR__) . '/config/release.php';
    $config = file_exists($configPath) ? require $configPath : [];

    assertTest(
        is_array($config) &&
        ($config['version'] ?? '') === '1.0.0' &&
        ($config['status'] ?? '') === 'GA' &&
        ($config['total_phases'] ?? 0) === 120 &&
        ($config['requirements']['runtime_external_dependencies'] ?? -1) === 0 &&
        ($config['diagnostics']['expected_migrations_count'] ?? 0) === 99,
        "Test 2: config/release.php configuration invariants and diagnostic settings"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n";
}

// Test 3: FrameworkReleaseManifest constants
try {
    assertTest(
        FrameworkReleaseManifest::VERSION === '1.0.0' &&
        FrameworkReleaseManifest::CODENAME === 'Sovereign Quantum & Physical Intelligence Fabric' &&
        FrameworkReleaseManifest::STATUS === 'GA' &&
        FrameworkReleaseManifest::TOTAL_PHASES === 120 &&
        FrameworkReleaseManifest::MINIMUM_PHP === '8.2.0' &&
        FrameworkReleaseManifest::AUTHOR === 'Md. Atiquzzaman Ashish' &&
        FrameworkReleaseManifest::LICENSE === 'MIT',
        "Test 3: FrameworkReleaseManifest immutable constants match official v1.0.0 specification"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n";
}

// Test 4: FrameworkReleaseManifest getReleaseInfo() and module category catalog
try {
    $info = FrameworkReleaseManifest::getReleaseInfo();
    $catalog = $info['module_catalog'] ?? [];

    assertTest(
        is_array($info) &&
        $info['version'] === '1.0.0' &&
        count($catalog) === 7 &&
        isset($catalog['Core & HMVC Fabric']) &&
        isset($catalog['Frontier Physical & Quantum Intelligence']),
        "Test 4: FrameworkReleaseManifest provides complete module catalog spanning all 120 phases"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 4: " . $e->getMessage() . "\n";
}

// Test 5: FrameworkReleaseManifest getBanner() ASCII formatting
try {
    $banner = FrameworkReleaseManifest::getBanner();

    assertTest(
        is_string($banner) &&
        str_contains($banner, 'NetFusion Framework v1.0.0') &&
        str_contains($banner, 'Global General Availability Release') &&
        str_contains($banner, 'Golden Master Certified'),
        "Test 5: FrameworkReleaseManifest getBanner() generates formatted ASCII presentation"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 5: " . $e->getMessage() . "\n";
}

// Test 6: GoldenMasterVerifier PHP runtime inspection
try {
    $verifier = new GoldenMasterVerifier();
    $res = $verifier->verifyAll(dirname(__DIR__));
    $phpCheck = $res['checks']['php_runtime'] ?? [];

    assertTest(
        ($phpCheck['passed'] ?? false) === true &&
        version_compare(PHP_VERSION, '8.2.0', '>='),
        "Test 6: GoldenMasterVerifier confirms PHP 8.2+ runtime environment compatibility"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 6: " . $e->getMessage() . "\n";
}

// Test 7: GoldenMasterVerifier zero runtime dependencies audit
try {
    $verifier = new GoldenMasterVerifier();
    $res = $verifier->verifyAll(dirname(__DIR__));
    $zeroDepCheck = $res['checks']['zero_dependencies'] ?? [];

    assertTest(
        ($zeroDepCheck['passed'] ?? false) === true &&
        ($zeroDepCheck['current'] ?? -1) === 0,
        "Test 7: GoldenMasterVerifier confirms zero external Composer runtime dependencies in core"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 7: " . $e->getMessage() . "\n";
}

// Test 8: GoldenMasterVerifier database migrations inventory
try {
    $verifier = new GoldenMasterVerifier();
    $res = $verifier->verifyAll(dirname(__DIR__));
    $migCheck = $res['checks']['migrations_inventory'] ?? [];

    assertTest(
        ($migCheck['passed'] ?? false) === true &&
        ($migCheck['actual'] ?? 0) >= 99,
        "Test 8: GoldenMasterVerifier confirms all 99 database migration schema files are intact"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 8: " . $e->getMessage() . "\n";
}

// Test 9: GoldenMasterVerifier overall integrity score & certification
try {
    $verifier = new GoldenMasterVerifier();
    $res = $verifier->verifyAll(dirname(__DIR__));

    assertTest(
        $res['score'] === 100 &&
        $res['is_certified'] === true &&
        $res['version'] === '1.0.0' &&
        $res['status'] === 'GA',
        "Test 9: GoldenMasterVerifier produces 100/100 score and certifies Golden Master readiness"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 9: " . $e->getMessage() . "\n";
}

// Test 10: Global helpers netfusion_version(), netfusion_manifest(), netfusion_verify()
try {
    $ver = netfusion_version();
    $manifest = netfusion_manifest();
    $verification = netfusion_verify(dirname(__DIR__));

    assertTest(
        function_exists('netfusion_version') &&
        function_exists('netfusion_manifest') &&
        function_exists('netfusion_verify') &&
        $ver === '1.0.0' &&
        ($manifest['total_phases'] ?? 0) === 120 &&
        ($verification['is_certified'] ?? false) === true,
        "Test 10: Global helpers netfusion_version(), netfusion_manifest(), and netfusion_verify() operational"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 10: " . $e->getMessage() . "\n";
}

echo "=== Phase 120 Result: {$testsPassed}/{$totalTests} Tests Passed ===\n";
exit($testsPassed === $totalTests ? 0 : 1);
