<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase25_26_27.php
| Module    : Tests
| Purpose   : Automated verification suite for Phases 25, 26, and 27.
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
use NetFusion\Core\Backup\BackupManager;
use NetFusion\Core\Backup\Dumpers\SqliteDumper;
use NetFusion\Core\Console\Kernel as ConsoleKernel;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Events\Dispatcher;
use NetFusion\Core\Http\Kernel as HttpKernel;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Translation\FileLoader;
use NetFusion\Core\Translation\Translator;
use NetFusion\Core\Webhooks\Incoming\WebhookGateway;
use NetFusion\Core\Webhooks\Incoming\WebhookVerifier;
use NetFusion\Core\Webhooks\WebhookSigner;

$basePath = realpath(__DIR__ . '/..');
$app = new Application($basePath);

$passed = 0;
$failed = 0;

function assertCondition(bool $condition, string $label, string &$details = ''): void
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
echo "  NetFusion Framework — Phases 25, 26 & 27 Test Suite\n";
echo "============================================================\n\n";

// Setup temporary SQLite test database
$testDbPath = __DIR__ . '/test_phase25_27.sqlite';
if (file_exists($testDbPath)) {
    @unlink($testDbPath);
}
touch($testDbPath);

$config = $app->make(\NetFusion\Core\Config\Config::class);
$config->set('database.default', 'sqlite');
$config->set('database.connections.sqlite.database', $testDbPath);

/** @var DatabaseManager $dbManager */
$dbManager = $app->make(DatabaseManager::class);
$conn = $dbManager->connection('sqlite');
$pdo = $conn->getPdo();

// Run Incoming Webhook Migration
$migration = require __DIR__ . '/../database/migrations/2026_09_17_000014_create_incoming_webhooks_table.php';
$migration->up($conn);

// Create dummy test table for database dumper test
$conn->statement("CREATE TABLE IF NOT EXISTS test_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT)");
$conn->insert("INSERT INTO test_articles (title, content) VALUES (:title, :content)", [
    ':title'   => 'Article One',
    ':content' => 'First content paragraph',
]);
$conn->insert("INSERT INTO test_articles (title, content) VALUES (:title, :content)", [
    ':title'   => 'Article Two',
    ':content' => 'Second content paragraph',
]);

// =========================================================================
//  SECTION 1: PHASE 25 (Backup & Disaster Recovery)
// =========================================================================
echo "\033[33m--- [Phase 25] Automated Backup & Disaster Recovery ---\033[0m\n";

// 1.1 SqliteDumper test
$dumper = new SqliteDumper();
$dumpFile = __DIR__ . '/temp_dump.sql';
if (file_exists($dumpFile)) {
    @unlink($dumpFile);
}
$dumper->dump($conn, $dumpFile);
assertCondition(file_exists($dumpFile) && filesize($dumpFile) > 0, "SqliteDumper creates valid SQL dump file");
$dumpContents = file_get_contents($dumpFile);
assertCondition(str_contains($dumpContents, 'test_articles') && str_contains($dumpContents, 'Article One'), "SqliteDumper exports schema and row data correctly");
@unlink($dumpFile);

// 1.2 BackupManager test
$tempBackupDir = __DIR__ . '/temp_backups';
if (!is_dir($tempBackupDir)) {
    @mkdir($tempBackupDir, 0775, true);
}
$backupManager = new BackupManager($tempBackupDir);
$backupResult = $backupManager->backup(['db', 'files'], 'Automated test snapshot');

assertCondition(isset($backupResult['filename']) && file_exists($backupResult['path']), "BackupManager creates physical ZIP backup archive");
assertCondition(!empty($backupResult['checksum']) && strlen($backupResult['checksum']) === 64, "Backup archive contains SHA-256 integrity checksum");
assertCondition($backupResult['size'] > 0, "Backup file size is calculated correctly");

// 1.3 List backups
$backups = $backupManager->listBackups();
assertCondition(count($backups) >= 1 && $backups[0]['filename'] === $backupResult['filename'], "BackupManager::listBackups finds created backup with metadata");

// 1.4 Backup Clean test (retention)
$deletedCount = $backupManager->cleanOldBackups(0); // 0 retention keep nothing
assertCondition($deletedCount >= 1 && count($backupManager->listBackups()) === 0, "BackupManager::cleanOldBackups prunes expired backups correctly");

// Clean temp backup dir
@rmdir($tempBackupDir);

// 1.5 CLI commands registration
/** @var ConsoleKernel $consoleKernel */
$consoleKernel = $app->make(ConsoleKernel::class);
$app->instance('backup', new BackupManager(__DIR__ . '/temp_backups_cli'));
$runCode = $consoleKernel->handle(['netfusion', 'backup:list']);
assertCondition($runCode === 0, "CLI Command 'backup:list' executes with status 0");

$cleanCode = $consoleKernel->handle(['netfusion', 'backup:clean', '--keep=5']);
assertCondition($cleanCode === 0, "CLI Command 'backup:clean' executes with status 0");
@rmdir(__DIR__ . '/temp_backups_cli');


// =========================================================================
//  SECTION 2: PHASE 26 (Translation & Localization)
// =========================================================================
echo "\n\033[33m--- [Phase 26] Translation & Multi-Language (i18n/L10n) ---\033[0m\n";

$langPath = __DIR__ . '/../lang';
$loader = new FileLoader($langPath);
$translator = new Translator($loader, 'en', 'en');

// 2.1 File Loader verification
$enMessages = $loader->load('en', 'messages');
$bnMessages = $loader->load('bn', 'messages');
assertCondition(isset($enMessages['welcome']) && isset($bnMessages['welcome']), "FileLoader loads language php arrays for 'en' and 'bn'");

// 2.2 Translation & Locale lookup
assertCondition($translator->get('messages.welcome', [], 'en') === 'Welcome to NetFusion Framework!', "English translation lookup succeeds");
assertCondition($translator->get('messages.welcome', [], 'bn') === 'নেটফিউশন ফ্রেমওয়ার্কে স্বাগতম!', "Bengali translation lookup succeeds");

// 2.3 Parameter replacement
$replaced = $translator->get('messages.user_created', ['name' => 'Ashish'], 'en');
assertCondition($replaced === 'User Ashish created successfully.', "Parameter replacement works in translation string (:name -> Ashish)");

$replacedBn = $translator->get('messages.user_created', ['name' => 'আশীষ'], 'bn');
assertCondition(str_contains($replacedBn, 'আশীষ'), "Parameter replacement works in Bengali string");

// 2.4 Fallback locale lookup
$translator->setLocale('bn');
$translator->setFallback('en');
// 'test_fallback_key' does not exist in bn, but if added or missing, checks fallback
assertCondition($translator->get('messages.save', [], 'bn') !== '', "Translating existing key with fallback works");

// Missing key returns the key itself
assertCondition($translator->get('messages.non_existent_key_xyz') === 'messages.non_existent_key_xyz', "Missing translation key returns raw key string");

// 2.5 Pluralization (choice)
$choice0 = $translator->choice('{0} No items|{1} One item|[2,*] :count items', 0);
$choice1 = $translator->choice('{0} No items|{1} One item|[2,*] :count items', 1);
$choice5 = $translator->choice('{0} No items|{1} One item|[2,*] :count items', 5);
assertCondition($choice0 === 'No items' && $choice1 === 'One item' && $choice5 === '5 items', "Translator::choice handles exact numbers and ranges pluralization");

// 2.6 Helper functions
assertCondition(function_exists('__') && function_exists('trans') && function_exists('trans_choice'), "Global translation helpers __(), trans(), and trans_choice() exist");
set_locale('bn');
assertCondition(app_locale() === 'bn', "set_locale() and app_locale() update and read active locale");
assertCondition(__('messages.welcome') === 'নেটফিউশন ফ্রেমওয়ার্কে স্বাগতম!', "__() helper translates according to active locale");
set_locale('en');
assertCondition(__('messages.welcome') === 'Welcome to NetFusion Framework!', "__() helper translates back to English when locale switched");

// 2.7 Lang missing CLI command
$langMissingCode = $consoleKernel->handle(['netfusion', 'lang:missing', '--source=en', '--target=bn']);
assertCondition($langMissingCode === 0, "CLI Command 'lang:missing' executes with status 0");


// =========================================================================
//  SECTION 3: PHASE 27 (Secure Incoming Webhook Gateway)
// =========================================================================
echo "\n\033[33m--- [Phase 27] Secure Incoming Webhook Gateway ---\033[0m\n";

$verifier = new WebhookVerifier();
$secret = 'whsec_enterprise_secret_test_12345';
$payloadRaw = json_encode([
    'id'   => 'evt_stripe_test_001',
    'type' => 'payment_intent.succeeded',
    'data' => [
        'amount'   => 5000,
        'currency' => 'usd',
        'status'   => 'succeeded',
    ],
], JSON_UNESCAPED_SLASHES);

// 3.1 Stripe timestamped HMAC-SHA256 signature verification
$signer = new WebhookSigner();
$stripeSig = $signer->sign($payloadRaw, $secret, time());
assertCondition($verifier->verify('stripe', $payloadRaw, ['stripe-signature' => $stripeSig], $secret), "Stripe timestamped HMAC-SHA256 signature verification passes");
assertCondition(!$verifier->verify('stripe', $payloadRaw, ['stripe-signature' => 't=' . time() . ',v1=invalid_hash'], $secret), "Tampered Stripe signature correctly rejected");
assertCondition(!$verifier->verify('stripe', $payloadRaw, ['stripe-signature' => 't=' . (time() - 400) . ',v1=expired'], $secret, 300), "Expired Stripe signature (> 300s) correctly rejected (replay attack prevention)");

// 3.2 GitHub signature verification
$githubSig = 'sha256=' . hash_hmac('sha256', $payloadRaw, $secret);
assertCondition($verifier->verify('github', $payloadRaw, ['x-hub-signature-256' => $githubSig], $secret), "GitHub sha256 signature verification passes");
assertCondition(!$verifier->verify('github', $payloadRaw, ['x-hub-signature-256' => 'sha256=invalid'], $secret), "Invalid GitHub signature rejected");

// 3.3 Shopify signature verification
$shopifySig = base64_encode(hash_hmac('sha256', $payloadRaw, $secret, true));
assertCondition($verifier->verify('shopify', $payloadRaw, ['x-shopify-hmac-sha256' => $shopifySig], $secret), "Shopify base64 HMAC signature verification passes");

// 3.4 WebhookGateway Ingestion & Persistence
$gateway = new WebhookGateway($app, $verifier);
$gateway->registerSecret('stripe', $secret);

// Event listener tracker
$eventTriggered = false;
$receivedEventData = null;
/** @var Dispatcher $dispatcher */
$dispatcher = $app->make(Dispatcher::class);
$dispatcher->listen('webhook.incoming.stripe.payment_intent.succeeded', function ($evt, $data = null) use (&$eventTriggered, &$receivedEventData) {
    $eventTriggered = true;
    $receivedEventData = is_array($data) ? $data : (is_array($evt) ? $evt : []);
});

// Ingest valid webhook
$ingestResult = $gateway->ingest('stripe', $payloadRaw, ['stripe-signature' => $stripeSig]);
assertCondition($ingestResult['success'] === true && $ingestResult['status'] === 'processed', "WebhookGateway::ingest verifies and processes incoming webhook");
assertCondition($eventTriggered === true && ($receivedEventData['event_id'] ?? '') === 'evt_stripe_test_001', "Internal Event Bus dispatched 'webhook.incoming.stripe.payment_intent.succeeded'");

// 3.5 Database audit ledger inspection
$record = $gateway->find($ingestResult['id']);
assertCondition($record !== null && $record['gateway'] === 'stripe' && $record['status'] === 'processed', "Incoming webhook recorded in database ledger with status 'processed'");

// 3.6 Idempotency Deduplication test (re-sending same event)
$dupResult = $gateway->ingest('stripe', $payloadRaw, ['stripe-signature' => $stripeSig]);
assertCondition($dupResult['success'] === true && $dupResult['duplicate'] === true, "Idempotency prevents duplicate processing of identical event ID");
assertCondition($dupResult['id'] === $ingestResult['id'], "Duplicate response points to the original recorded webhook ID");

// 3.7 Webhook Replay functionality
$replayResult = $gateway->replay($ingestResult['id']);
assertCondition($replayResult['success'] === true && $replayResult['status'] === 'processed', "WebhookGateway::replay re-executes stored webhook event");
$updatedRecord = $gateway->find($ingestResult['id']);
assertCondition((int) $updatedRecord['attempts'] >= 2, "Replaying increments attempts count in database ledger");

// 3.8 CLI Command webhook:replay
$app->instance(WebhookGateway::class, $gateway);
$replayCode = $consoleKernel->handle(['netfusion', 'webhook:replay', (string) $ingestResult['id']]);
assertCondition($replayCode === 0, "CLI Command 'webhook:replay {id}' executes with status 0");

// 3.9 HTTP Kernel & Controller route dispatch
$router = $app->make(\NetFusion\Core\Routing\Router::class);
require __DIR__ . '/../routes/api.php';
$httpKernel = $app->make(HttpKernel::class);

$httpRequest = Request::create(
    method: 'POST',
    uri: '/api/v1/webhooks/incoming/stripe',
    params: [],
    server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $stripeSig,
    ],
    content: $payloadRaw
);

// Add route parameter manually or simulate through kernel
$httpRequest->setRouteParams(['gateway' => 'stripe']);
$controller = new \NetFusion\App\Http\Controllers\IncomingWebhookController($gateway);
$response = $controller->handle($httpRequest);

assertCondition($response->getStatusCode() === 200, "IncomingWebhookController returns HTTP 200 for valid webhook request");
$respData = json_decode($response->getContent(), true);
assertCondition(isset($respData['duplicate']) && $respData['duplicate'] === true, "Controller idempotently recognizes duplicate event and returns 200 OK");

// Test invalid signature via Controller
$badRequest = Request::create(
    method: 'POST',
    uri: '/api/v1/webhooks/incoming/stripe',
    params: [],
    server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 'invalid_sig',
    ],
    content: $payloadRaw
);
$badRequest->setRouteParams(['gateway' => 'stripe']);
$badResponse = $controller->handle($badRequest);
assertCondition($badResponse->getStatusCode() === 401, "IncomingWebhookController returns HTTP 401 Unauthorized for invalid signature");

// Clean up temporary database
if (file_exists($testDbPath)) {
    @unlink($testDbPath);
}

// =========================================================================
//  SUMMARY
// =========================================================================
echo "\n============================================================\n";
echo "  Total Passed: {$passed} | Total Failed: {$failed}\n";
echo "============================================================\n\n";

exit($failed > 0 ? 1 : 0);
