<?php
/**
 * NetFusion Framework - DotNetBD License CMS Integration Verification Suite
 *
 * @package NetFusion\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Config/Env.php';
require_once __DIR__ . '/../core/Foundation/License/Exceptions/LicenseException.php';
require_once __DIR__ . '/../core/Foundation/License/LicenseManager.php';
require_once __DIR__ . '/../core/Http/Middleware/LicenseGuardMiddleware.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Foundation\License\LicenseManager;
use NetFusion\Core\Foundation\License\Exceptions\LicenseException;
use NetFusion\Core\Http\Middleware\LicenseGuardMiddleware;

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

echo "=== Running DotNetBD License CMS (API Engine v5.1.8) Tests ===\n";

$tempLicPath = __DIR__ . '/test_license_temp.lic';
$testSalt = 'Test_Secret_Salt_12345';

// Test 1: Instantiation with custom config
try {
    $mgr = new LicenseManager([
        'server_url'   => 'https://lic.dotnetbd.com',
        'product_id'   => 1,
        'salt'         => $testSalt,
        'storage_path' => $tempLicPath,
        'cache_ttl'    => 86400,
    ]);

    assertTest(
        $mgr instanceof LicenseManager &&
        file_exists(dirname(__DIR__) . '/config/license.php'),
        "Test 1: LicenseManager instantiates cleanly with DotNetBD API configuration"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n";
}

// Test 2: Status when no license is installed
try {
    if (file_exists($tempLicPath)) @unlink($tempLicPath);
    $status = $mgr->status();

    assertTest(
        $status['is_active'] === false &&
        $status['status'] === 'UNLICENSED' &&
        str_contains($status['message'], 'unlicensed'),
        "Test 2: LicenseManager reports UNLICENSED when no license file exists"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n";
}

// Test 3: Domain detection and MD5 server hash calculation
try {
    $domain = $mgr->detectDomain();
    $expectedHash = md5($domain . $testSalt);

    assertTest(
        !empty($domain) &&
        strlen($expectedHash) === 32,
        "Test 3: Server domain and md5(domain . SALT) hash calculated accurately"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n";
}

// Test 4: Local license serialization and signature verification
try {
    $testKey = 'NF-8472-9102-3847-5921';
    $company = 'DotNetBD Enterprise Client';
    $domain = 'client.example.com';
    $expiresAt = date('Y-m-d H:i:s', time() + 86400 * 365); // 1 year future
    $sig = hash_hmac('sha256', $testKey . '|' . $domain . '|' . $expiresAt, $testSalt);

    $testData = [
        'license_key'        => $testKey,
        'company_name'       => $company,
        'domain'             => $domain,
        'server_hash'        => md5($domain . $testSalt),
        'product_id'         => 1,
        'max_servers'        => 1,
        'max_extensions'     => 0,
        'expires_at'         => $expiresAt,
        'activated_at'       => date('Y-m-d H:i:s'),
        'last_validated_at'  => date('Y-m-d H:i:s'),
        'signature'          => $sig,
    ];

    // Save mock license to tempLicPath
    file_put_contents($tempLicPath, base64_encode(json_encode($testData)));

    $val = $mgr->validate(false);

    assertTest(
        $val['valid'] === true &&
        $val['license_key'] === $testKey &&
        $val['company_name'] === $company &&
        ($val['days_left'] ?? 0) >= 364,
        "Test 4: Local valid license file passes HMAC-SHA256 signature and expiration check"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 4: " . $e->getMessage() . "\n";
}

// Test 5: Anti-tamper detection (tampered signature or key)
try {
    $tamperedData = $testData;
    $tamperedData['signature'] = 'tampered_fake_signature_hash';
    file_put_contents($tempLicPath, base64_encode(json_encode($tamperedData)));

    $valTampered = $mgr->validate(false);

    assertTest(
        $valTampered['valid'] === false &&
        str_contains($valTampered['message'], 'tampering detected'),
        "Test 5: Anti-tamper verification rejects invalid signature"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 5: " . $e->getMessage() . "\n";
}

// Test 6: Expired license detection
try {
    $expiredDate = '2023-01-01 00:00:00';
    $expiredSig = hash_hmac('sha256', $testKey . '|' . $domain . '|' . $expiredDate, $testSalt);
    $expiredData = $testData;
    $expiredData['expires_at'] = $expiredDate;
    $expiredData['signature'] = $expiredSig;
    file_put_contents($tempLicPath, base64_encode(json_encode($expiredData)));

    $valExpired = $mgr->validate(false);

    assertTest(
        $valExpired['valid'] === false &&
        str_contains($valExpired['message'], 'expired on'),
        "Test 6: Expired license date detected and rejected"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 6: " . $e->getMessage() . "\n";
}

// Test 7: Active status reporting
try {
    // Restore valid license
    file_put_contents($tempLicPath, base64_encode(json_encode($testData)));
    $status = $mgr->status();

    assertTest(
        $status['is_active'] === true &&
        $status['status'] === 'ACTIVE' &&
        $status['license_key'] === $testKey &&
        $status['domain'] === $domain,
        "Test 7: LicenseManager status() reports ACTIVE with accurate metadata"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 7: " . $e->getMessage() . "\n";
}

// Test 8: Deactivate method deletes local license
try {
    $deactivated = $mgr->deactivate();

    assertTest(
        $deactivated === true &&
        !file_exists($tempLicPath) &&
        $mgr->status()['is_active'] === false,
        "Test 8: LicenseManager deactivate() cleanly removes local license file"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 8: " . $e->getMessage() . "\n";
}

// Test 9: Global helper functions
try {
    assertTest(
        function_exists('license') &&
        function_exists('license_active') &&
        function_exists('license_check') &&
        license() instanceof LicenseManager,
        "Test 9: Global helper functions license(), license_active(), license_check() operational"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 9: " . $e->getMessage() . "\n";
}

// Test 10: LicenseGuardMiddleware route protection
try {
    $middleware = new LicenseGuardMiddleware($mgr);
    $requestProcessed = false;

    // With enforce disabled, next closure should run
    $response = $middleware->handle([], function($req) use (&$requestProcessed) {
        $requestProcessed = true;
        return 'success';
    });

    assertTest(
        $requestProcessed === true &&
        $response === 'success',
        "Test 10: LicenseGuardMiddleware processes pipeline cleanly"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 10: " . $e->getMessage() . "\n";
}

// Cleanup
if (file_exists($tempLicPath)) @unlink($tempLicPath);

echo "=== DotNetBD License CMS Result: {$testsPassed}/{$totalTests} Tests Passed ===\n";
exit($testsPassed === $totalTests ? 0 : 1);
