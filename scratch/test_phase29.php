<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase29.php
| Module    : Tests
| Purpose   : Automated verification suite for Phase 29 (API Gateway, Rate Limiter & PAT).
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

use NetFusion\App\Models\User;
use NetFusion\Core\Application;
use NetFusion\Core\Auth\Tokens\AuthenticateWithApiToken;
use NetFusion\Core\Auth\Tokens\NewAccessToken;
use NetFusion\Core\Auth\Tokens\PersonalAccessToken;
use NetFusion\Core\Auth\Tokens\TokenManager;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Console\Kernel as ConsoleKernel;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Http\Middleware\ThrottleRequests;
use NetFusion\Core\Http\RateLimiting\Limit;
use NetFusion\Core\Http\RateLimiting\RateLimiter;
use NetFusion\Core\Http\RateLimiting\TokenBucket;
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
echo "  NetFusion Verification Suite — Phase 29: API Gateway & PAT\n";
echo "============================================================\n";

// =========================================================================
//  SECTION 1: TokenBucket Algorithm
// =========================================================================
echo "\n\033[33m--- [Section 1] TokenBucket Algorithm ---\033[0m\n";

$bucket = new TokenBucket(capacity: 10, refillRate: 2.0);
assertTest($bucket->getCapacity() === 10, 'TokenBucket reports correct capacity (10)');
assertTest($bucket->getTokens() === 10, 'TokenBucket starts with full capacity (10)');
assertTest($bucket->consume(5) === true, 'Successfully consumed 5 tokens');
assertTest($bucket->getTokens() === 5, 'Remaining tokens is 5 after consuming 5');
assertTest($bucket->consume(5) === true, 'Successfully consumed remaining 5 tokens');
assertTest($bucket->consume(1) === false, 'Cannot consume token when bucket is depleted');
assertTest($bucket->timeUntilAvailable(1) > 0, 'timeUntilAvailable calculates positive waiting time');

// State serialization
$state = $bucket->getState();
assertTest(isset($state['capacity'], $state['refill_rate'], $state['tokens'], $state['last_refill']), 'getState() exports complete bucket structure');
$restoredBucket = TokenBucket::fromState($state);
assertTest($restoredBucket->getCapacity() === 10, 'fromState() restores correct capacity');
assertTest($restoredBucket->getRefillRate() === 2.0, 'fromState() restores refill rate');

// =========================================================================
//  SECTION 2: Limit Specification Object
// =========================================================================
echo "\n\033[33m--- [Section 2] Limit Specification Object ---\033[0m\n";

$limitMin = Limit::perMinute(60)->by('ip:127.0.0.1');
assertTest($limitMin->maxAttempts === 60 && $limitMin->decaySeconds === 60, 'Limit::perMinute sets 60 attempts / 60 seconds');
assertTest($limitMin->key === 'ip:127.0.0.1', 'Limit::by binds key correctly');

$limitHour = Limit::perHour(1000);
assertTest($limitHour->maxAttempts === 1000 && $limitHour->decaySeconds === 3600, 'Limit::perHour sets 1000 attempts / 3600 seconds');

$limitDay = Limit::perDay(10000);
assertTest($limitDay->maxAttempts === 10000 && $limitDay->decaySeconds === 86400, 'Limit::perDay sets 10000 attempts / 86400 seconds');

$limitNone = Limit::none();
assertTest($limitNone->maxAttempts === PHP_INT_MAX, 'Limit::none sets unlimited attempts');

// =========================================================================
//  SECTION 3: RateLimiter Service
// =========================================================================
echo "\n\033[33m--- [Section 3] RateLimiter Service ---\033[0m\n";

$limiter = new RateLimiter();
$testKey = 'client:test:1';

assertTest($limiter->attempts($testKey) === 0, 'Initial attempts count is 0');
$limiter->hit($testKey, 60);
assertTest($limiter->attempts($testKey) === 1, 'hit() increments attempts count to 1');
assertTest($limiter->remaining($testKey, 5) === 4, 'remaining() returns 4 of 5');
assertTest($limiter->tooManyAttempts($testKey, 5) === false, 'tooManyAttempts() is false under threshold');

$limiter->hit($testKey, 60);
$limiter->hit($testKey, 60);
$limiter->hit($testKey, 60);
$limiter->hit($testKey, 60);
assertTest($limiter->attempts($testKey) === 5, 'attempts count reached 5');
assertTest($limiter->tooManyAttempts($testKey, 5) === true, 'tooManyAttempts() is true when threshold met');

assertTest($limiter->resetAttempts($testKey) === true, 'resetAttempts() successfully clears key');
assertTest($limiter->attempts($testKey) === 0, 'Attempts count is 0 after reset');

// Named limiter
$limiter->for('custom-api', function (Request $r) {
    return Limit::perMinute(30)->by('custom:' . $r->ip());
});
assertTest($limiter->limiter('custom-api') !== null, 'Named limiter callback registered and retrieved');

// Token bucket via RateLimiter
$tbKey = 'tb:client:99';
$tbConsumed = $limiter->consumeToken($tbKey, 5, 1.0, 3);
assertTest($tbConsumed === true, 'consumeToken() consumes 3 tokens from capacity 5');
assertTest($limiter->tokenBucketRemaining($tbKey, 5, 1.0) === 2, 'tokenBucketRemaining() reports 2 tokens remaining');

// =========================================================================
//  SECTION 4: ThrottleRequests Middleware
// =========================================================================
echo "\n\033[33m--- [Section 4] ThrottleRequests Middleware ---\033[0m\n";

$throttleMiddleware = new ThrottleRequests($limiter, '3,1'); // 3 attempts per minute
$req = Request::create(uri: '/api/v1/resource', method: 'GET', server: ['REMOTE_ADDR' => '192.168.1.50']);

$handler = function (Request $r) {
    return Response::json(['status' => 'success', 'data' => 'allowed']);
};

// Request 1
$res1 = $throttleMiddleware->handle($req, $handler);
assertTest($res1->getStatusCode() === 200, 'Throttle request 1 succeeds (200)');
assertTest($res1->getHeader('X-RateLimit-Limit') === '3', 'X-RateLimit-Limit header is 3');
assertTest($res1->getHeader('X-RateLimit-Remaining') === '2', 'X-RateLimit-Remaining header is 2');

// Request 2
$res2 = $throttleMiddleware->handle($req, $handler);
assertTest($res2->getStatusCode() === 200, 'Throttle request 2 succeeds (200)');
assertTest($res2->getHeader('X-RateLimit-Remaining') === '1', 'X-RateLimit-Remaining header is 1');

// Request 3
$res3 = $throttleMiddleware->handle($req, $handler);
assertTest($res3->getStatusCode() === 200, 'Throttle request 3 succeeds (200)');
assertTest($res3->getHeader('X-RateLimit-Remaining') === '0', 'X-RateLimit-Remaining header is 0');

// Request 4 -> Exceeded!
$res4 = $throttleMiddleware->handle($req, $handler);
assertTest($res4->getStatusCode() === 429, 'Throttle request 4 blocked with 429 Too Many Requests');
assertTest($res4->hasHeader('Retry-After'), '429 response includes Retry-After header');
assertTest($res4->getHeader('X-RateLimit-Remaining') === '0', '429 response includes X-RateLimit-Remaining: 0');

// =========================================================================
//  SECTION 5: Database Setup & Migrations
// =========================================================================
echo "\n\033[33m--- [Section 5] Database Setup & Migration ---\033[0m\n";

$testDbPath = __DIR__ . '/test_phase29.sqlite';
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

// Create users table for testing
$conn->statement("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )
");

// Run Personal Access Tokens migration
$migration = require __DIR__ . '/../database/migrations/2026_09_18_000015_create_personal_access_tokens_table.php';
$migration->up($conn);

assertTest(true, 'Database migrated with users & personal_access_tokens tables');

// Create a test user
$user = User::create([
    'name'       => 'Ashish Lead Dev',
    'email'      => 'ashish@dotnetbd.com',
    'password'   => password_hash('secret123', PASSWORD_BCRYPT),
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
]);
assertTest($user->getKey() !== null, 'User created with ID: ' . $user->getKey());

// =========================================================================
//  SECTION 6: Personal Access Token Model & Scopes
// =========================================================================
echo "\n\033[33m--- [Section 6] PAT Model & Scope Verifications ---\033[0m\n";

$pat = new PersonalAccessToken([
    'tokenable_type' => User::class,
    'tokenable_id'   => $user->getKey(),
    'name'           => 'dev-token',
    'token'          => hash('sha256', 'dummy-token'),
    'abilities'      => json_encode(['users:read', 'orders:*']),
    'expires_at'     => null,
]);

assertTest($pat->can('users:read') === true, 'PAT can exact scope: users:read');
assertTest($pat->can('users:write') === false, 'PAT cannot missing scope: users:write');
assertTest($pat->can('orders:read') === true, 'PAT can wildcard namespace scope: orders:read');
assertTest($pat->can('orders:delete') === true, 'PAT can wildcard namespace scope: orders:delete');
assertTest($pat->cant('billing:read') === true, 'PAT cant() correctly identifies unauthorized scope');
assertTest($pat->isExpired() === false, 'PAT with null expires_at is not expired');

$expiredPat = new PersonalAccessToken([
    'expires_at' => date('Y-m-d H:i:s', time() - 3600),
]);
assertTest($expiredPat->isExpired() === true, 'PAT with past expires_at isExpired() is true');

// =========================================================================
//  SECTION 7: HasApiTokens Trait & TokenManager
// =========================================================================
echo "\n\033[33m--- [Section 7] HasApiTokens Trait & TokenManager ---\033[0m\n";

/** @var TokenManager $tokenManager */
$tokenManager = $app->make('token.manager');

// Create token via User model using HasApiTokens trait
$newToken = $user->createToken(
    name     : 'mobile-app',
    abilities: ['profile:read', 'reports:*'],
    expiresAt: (new DateTimeImmutable())->modify('+7 days')
);

assertTest($newToken instanceof NewAccessToken, 'createToken() returns instance of NewAccessToken');
assertTest(str_starts_with($newToken->plainTextToken, 'nfp_'), 'Plain-text token starts with nfp_ prefix');
assertTest(strlen($newToken->plainTextToken) >= 64, 'Plain-text token has high cryptographic entropy (>= 64 chars)');

// Check DB storage: verify plainTextToken is NOT in DB, but SHA-256 hash is
$tokenHash = hash('sha256', $newToken->plainTextToken);
$dbRow = $conn->selectOne("SELECT * FROM personal_access_tokens WHERE token = :hash", [':hash' => $tokenHash]);
assertTest($dbRow !== null, 'Hashed token found in personal_access_tokens table');

$rawCheck = $conn->selectOne("SELECT * FROM personal_access_tokens WHERE token = :plain", [':plain' => $newToken->plainTextToken]);
assertTest($rawCheck === null, 'Security: Plain-text token is NEVER stored in database');

// Retrieve tokens for user
$userTokens = $user->tokens();
assertTest(count($userTokens) === 1, 'User has exactly 1 token');
assertTest($userTokens[0]->getAttribute('name') === 'mobile-app', 'Token name matches "mobile-app"');

// TokenManager: find token
$foundToken = $tokenManager->findToken($newToken->plainTextToken);
assertTest($foundToken !== null, 'TokenManager::findToken finds token by plain-text');
assertTest($foundToken->getAttribute('last_used_at') !== null, 'TokenManager updates last_used_at upon resolution');

// Model ability check with token attached
$user->withAccessToken($foundToken);
assertTest($user->currentAccessToken() !== null, 'User has currentAccessToken attached');
assertTest($user->tokenCan('profile:read') === true, 'User tokenCan profile:read is true');
assertTest($user->tokenCan('reports:view') === true, 'User tokenCan reports:view is true (wildcard reports:*)');
assertTest($user->tokenCan('admin:root') === false, 'User tokenCan admin:root is false');

// =========================================================================
//  SECTION 8: AuthenticateWithApiToken Middleware
// =========================================================================
echo "\n\033[33m--- [Section 8] AuthenticateWithApiToken Middleware ---\033[0m\n";

$authMiddleware = new AuthenticateWithApiToken($tokenManager);

// 1. Missing Authorization header
$noAuthReq = Request::create(uri: '/api/v1/me', method: 'GET');
$noAuthRes = $authMiddleware->handle($noAuthReq, $handler);
assertTest($noAuthRes->getStatusCode() === 401, 'Request without token returns 401 Unauthorized');

// 2. Invalid Bearer token
$badAuthReq = Request::create(uri: '/api/v1/me', method: 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer nfp_invalid_token_xyz']);
$badAuthRes = $authMiddleware->handle($badAuthReq, $handler);
assertTest($badAuthRes->getStatusCode() === 401, 'Request with bad token returns 401 Unauthorized');

// 3. Valid Bearer token
$goodAuthReq = Request::create(uri: '/api/v1/me', method: 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $newToken->plainTextToken]);
$goodAuthRes = $authMiddleware->handle($goodAuthReq, function (Request $r) {
    return Response::json([
        'user_id' => $r->getAttribute('token_user_id'),
        'user_name' => $r->getAttribute('user')?->name,
    ]);
});
assertTest($goodAuthRes->getStatusCode() === 200, 'Request with valid token succeeds (200)');
$goodBody = json_decode($goodAuthRes->getContent(), true);
assertTest($goodBody['user_id'] == $user->getKey(), 'Request attributes contain authenticated user ID');
assertTest($goodBody['user_name'] === 'Ashish Lead Dev', 'Request attributes contain resolved User model');

// 4. Ability scope enforcement: route requires 'reports:export' (satisfied by reports:*)
$scopedMiddlewarePass = new AuthenticateWithApiToken($tokenManager, 'reports:export');
$scopedPassRes = $scopedMiddlewarePass->handle($goodAuthReq, $handler);
assertTest($scopedPassRes->getStatusCode() === 200, 'Middleware permits token with matching wildcard scope');

// 5. Ability scope enforcement: route requires 'billing:manage' (not possessed)
$scopedMiddlewareFail = new AuthenticateWithApiToken($tokenManager, 'billing:manage');
$scopedFailRes = $scopedMiddlewareFail->handle($goodAuthReq, $handler);
assertTest($scopedFailRes->getStatusCode() === 403, 'Middleware returns 403 Forbidden for missing required scope');

// =========================================================================
//  SECTION 9: Token Revocation
// =========================================================================
echo "\n\033[33m--- [Section 9] Token Revocation ---\033[0m\n";

$revoked = $tokenManager->revokeToken($foundToken->getKey());
assertTest($revoked === true, 'Token successfully revoked by ID');

$revokedCheck = $tokenManager->findToken($newToken->plainTextToken);
assertTest($revokedCheck === null, 'Revoked token can no longer be resolved (returns null)');

// Issue two tokens and test revokeAllFor
$tokenA = $user->createToken('token-a');
$tokenB = $user->createToken('token-b');
assertTest(count($user->tokens()) === 2, 'User has 2 newly issued tokens');

$revokedCount = $tokenManager->revokeAllFor(User::class, $user->getKey());
assertTest($revokedCount === 2, 'revokeAllFor() revoked all 2 tokens for user');
assertTest(count($user->tokens()) === 0, 'User has 0 tokens remaining');

// =========================================================================
//  SECTION 10: CLI Commands Integration
// =========================================================================
echo "\n\033[33m--- [Section 10] CLI Commands (token:create, token:list, token:revoke) ---\033[0m\n";

/** @var ConsoleKernel $console */
$console = $app->make(ConsoleKernel::class);

// Test token:create
ob_start();
$createCode = $console->handle(['netfusion', 'token:create', (string) $user->getKey(), '--name=ci-token', '--scopes=test:run']);
$createOutput = ob_get_clean();

assertTest($createCode === 0, 'CLI token:create exits with code 0');
assertTest(str_contains($createOutput, 'Personal Access Token created successfully'), 'CLI token:create reports success');
assertTest(str_contains($createOutput, 'nfp_'), 'CLI token:create outputs plain-text token');

// Extract plain text token from output
preg_match('/nfp_[a-f0-9]+/', $createOutput, $matches);
$cliPlainToken = $matches[0] ?? '';
assertTest($cliPlainToken !== '', 'Successfully captured plain-text token from CLI output');

// Test token:list
ob_start();
$listCode = $console->handle(['netfusion', 'token:list', (string) $user->getKey()]);
$listOutput = ob_get_clean();

assertTest($listCode === 0, 'CLI token:list exits with code 0');
assertTest(str_contains($listOutput, 'ci-token'), 'CLI token:list displays created token');

// Find ID of token to revoke
$cliToken = $tokenManager->findToken($cliPlainToken);
$cliTokenId = $cliToken ? $cliToken->getKey() : 1;

// Test token:revoke by ID
ob_start();
$revokeCode = $console->handle(['netfusion', 'token:revoke', (string) $cliTokenId]);
$revokeOutput = ob_get_clean();

assertTest($revokeCode === 0, 'CLI token:revoke exits with code 0');
assertTest(str_contains($revokeOutput, 'revoked successfully'), 'CLI token:revoke reports token revoked');

// Clean up SQLite DB
if (file_exists($testDbPath)) {
    @unlink($testDbPath);
}

// =========================================================================
//  SUMMARY
// =========================================================================
echo "\n============================================================\n";
echo "  Verification Results: Phase 29\n";
echo "  Total:  " . ($passed + $failed) . "\n";
echo "  Passed: \033[32m{$passed}\033[0m\n";
echo "  Failed: \033[31m{$failed}\033[0m\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
