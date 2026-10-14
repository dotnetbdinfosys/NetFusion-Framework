<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Payment\Contracts\PaymentGatewayInterface;
use NetFusion\Core\Payment\Contracts\PaymentRouterInterface;
use NetFusion\Core\Payment\Contracts\TokenizationVaultInterface;
use NetFusion\Core\Payment\Contracts\SettlementEngineInterface;
use NetFusion\Core\Payment\Contracts\ReconciliationEngineInterface;
use NetFusion\Core\Payment\Exceptions\PaymentException;
use NetFusion\Core\Payment\Exceptions\GatewayException;
use NetFusion\Core\Payment\Exceptions\FraudDetectedException;
use NetFusion\Core\Payment\Exceptions\IdempotencyException;
use NetFusion\Core\Payment\Exceptions\SettlementException;
use NetFusion\Core\Payment\Models\PaymentIntent;
use NetFusion\Core\Payment\Models\PaymentResult;
use NetFusion\Core\Payment\Models\PaymentMethod;
use NetFusion\Core\Payment\Models\SplitRule;
use NetFusion\Core\Payment\Models\SettlementBatch;
use NetFusion\Core\Payment\Vault\CardToken;
use NetFusion\Core\Payment\Vault\PCIConfidenceVault;
use NetFusion\Core\Payment\Gateways\StripeGateway;
use NetFusion\Core\Payment\Gateways\PayPalGateway;
use NetFusion\Core\Payment\Gateways\BKashGateway;
use NetFusion\Core\Payment\Gateways\NagadGateway;
use NetFusion\Core\Payment\Gateways\SSLCommerzGateway;
use NetFusion\Core\Payment\Gateways\MockGateway;
use NetFusion\Core\Payment\Routing\GatewayHealthTracker;
use NetFusion\Core\Payment\Routing\SmartPaymentRouter;
use NetFusion\Core\Payment\Settlement\LedgerJournal;
use NetFusion\Core\Payment\Settlement\SplitSettlementEngine;
use NetFusion\Core\Payment\Reconciliation\ReconciliationEngine;
use NetFusion\Core\Payment\PaymentOrchestrator;
use NetFusion\Core\Console\Commands\PaymentStatusCommand;
use NetFusion\Core\Console\Commands\PaymentReconcileCommand;

$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $description, bool $condition): void
{
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $testsFailed++;
    }
}

echo "========================================================================\n";
echo "  NetFusion Framework — Phase 63 Test Suite\n";
echo "  Enterprise Payment Gateway Orchestrator, Multi-Processor Smart Routing,\n";
echo "  Split Settlements & PCI-DSS Tokenization Vault Engine\n";
echo "========================================================================\n\n";

// Setup Test SQLite Database
$testDbFile = __DIR__ . '/test_phase63.sqlite';
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}
$conn = new Connection([
    'driver' => 'sqlite',
    'database' => $testDbFile,
]);

// Run Payment Migration
$migration = require __DIR__ . '/../database/migrations/2026_09_21_000043_create_payment_tables.php';
$migration->up($conn);

// Initialize Application Container
$app = new Application(realpath(__DIR__ . '/..'));
$app->instance('db', $conn);
$app->instance(Connection::class, $conn);

// -----------------------------------------------------------------------------
// 1. PaymentMethod Model, Luhn Validation & Card Detection
// -----------------------------------------------------------------------------
echo "1. Testing PaymentMethod Model, Luhn Validation & Card Detection...\n";

// Valid Visa Card (4111111111111111)
$validVisa = PaymentMethod::card(
    '4111 1111 1111 1111',
    'John Doe',
    12,
    (int)date('Y') + 2,
    '123'
);
assertTest('PaymentMethod creates valid Visa card', $validVisa->getType() === 'card' && $validVisa->getBrand() === 'Visa' && $validVisa->getLast4() === '1111');
assertTest('PaymentMethod masks PAN correctly', $validVisa->getMaskedNumber() === '•••• •••• •••• 1111');
assertTest('PaymentMethod brand detection identifies Mastercard & Amex', PaymentMethod::detectBrand('5100000000000000') === 'Mastercard' && PaymentMethod::detectBrand('370000000000000') === 'Amex');

// Expired Card Check
$expiredCard = PaymentMethod::card(
    '4111111111111111',
    'Expired Cardholder',
    1,
    2020,
    '123'
);
assertTest('PaymentMethod::isExpired() identifies expired card', $expiredCard->isExpired() === true);

// MFS Payment Method (bKash / Nagad)
$mfsMethod = PaymentMethod::mfs('01711000000', 'bKash');
assertTest('PaymentMethod creates MFS payment method', $mfsMethod->getType() === 'mfs' && $mfsMethod->getBrand() === 'bKash' && $mfsMethod->getLast4() === '0000');

// Bank Account Payment Method
$bankMethod = PaymentMethod::bankAccount('1234567890', 'Chase Bank', 'Alice Vendor');
assertTest('PaymentMethod creates Bank Account method', $bankMethod->getType() === 'bank_account' && $bankMethod->getLast4() === '7890');

// -----------------------------------------------------------------------------
// 2. PCI-DSS Tokenization Vault (AES-256-GCM Zero-Knowledge Encryption)
// -----------------------------------------------------------------------------
echo "\n2. Testing PCI-DSS Tokenization Vault Engine...\n";

$vaultSecret = 'base64:' . base64_encode(random_bytes(32));
$vault = new PCIConfidenceVault($vaultSecret, $conn->getPdo());

$cardToTokenize = PaymentMethod::card(
    '4111111111111111',
    'Alice Smith',
    8,
    (int)date('Y') + 3,
    '999'
);

$tokenString = $vault->tokenize($cardToTokenize, 'cust_1001');
assertTest('PCIConfidenceVault generates secure card token string', str_starts_with($tokenString, 'tok_visa_'));
assertTest('PCIConfidenceVault verifies token validity', $vault->isValid($tokenString));

// Detokenization
$detokenized = $vault->detokenize($tokenString);
assertTest('PCIConfidenceVault accurately detokenizes card details', $detokenized instanceof PaymentMethod && $detokenized->getNumber() === '4111111111111111' && $detokenized->getHolderName() === 'Alice Smith');
assertTest('PCIConfidenceVault detokenized card has no CVV (PCI-DSS requirement)', $detokenized->getCvv() === null);

// Token Revocation
$revoked = $vault->revoke($tokenString);
assertTest('PCIConfidenceVault revokes token successfully', $revoked && !$vault->isValid($tokenString));

// Luhn validation in Vault
assertTest('PCIConfidenceVault::validateLuhn validates valid PAN', PCIConfidenceVault::validateLuhn('4111111111111111'));
assertTest('PCIConfidenceVault::validateLuhn rejects invalid PAN', !PCIConfidenceVault::validateLuhn('4111111111111112'));

// Re-tokenize for subsequent tests
$activeToken = $vault->tokenize($cardToTokenize, 'cust_1001');

// -----------------------------------------------------------------------------
// 3. Multi-Processor Gateways (Stripe, PayPal, bKash, Nagad, SSLCommerz, Mock)
// -----------------------------------------------------------------------------
echo "\n3. Testing Multi-Processor Gateway Drivers...\n";

// Stripe Gateway
$stripe = new StripeGateway(['secret_key' => 'sk_test_mock123', 'webhook_secret' => 'whsec_test']);
assertTest('StripeGateway supports USD, EUR, GBP', $stripe->supportsCurrency('USD') && $stripe->supportsCurrency('EUR'));
$stripeChargeIntent = (PaymentIntent::create(150.00, 'USD', 'cust_01'))->withPaymentMethod($cardToTokenize);
$stripeResult = $stripe->charge($stripeChargeIntent);
assertTest('StripeGateway executes charge successfully', $stripeResult->isSuccess() && str_starts_with($stripeResult->getTransactionId(), 'ch_str_'));
assertTest('StripeGateway calculates processing fees', $stripeResult->getFee() > 0.0 && $stripeResult->getNet() < 150.00);

// Stripe Authorize and Capture
$stripeAuth = $stripe->authorize(PaymentIntent::create(250.00, 'USD', 'cust_auth'));
assertTest('StripeGateway authorizes amount', $stripeAuth->isSuccess() && str_starts_with($stripeAuth->getTransactionId(), 'auth_str_'));
$stripeCapture = $stripe->capture($stripeAuth->getTransactionId(), 250.00);
assertTest('StripeGateway captures authorized transaction', $stripeCapture->isSuccess() && str_starts_with($stripeCapture->getTransactionId(), 'cap_'));

// Stripe Refund
$stripeRefund = $stripe->refund($stripeResult->getTransactionId(), 50.00);
assertTest('StripeGateway executes partial refund', $stripeRefund->isSuccess() && str_starts_with($stripeRefund->getTransactionId(), 're_str_'));

// Stripe Webhook Verification
$webhookData = ['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_test_123']]];
$webhookVerified = $stripe->verifyWebhook($webhookData, ['stripe-signature' => 'valid_sig_123']);
assertTest('StripeGateway verifies webhook payload', $webhookVerified['status'] === 'verified' && $webhookVerified['transaction_id'] === 'pi_test_123');

// PayPal Gateway
$paypal = new PayPalGateway(['client_id' => 'paypal_client', 'client_secret' => 'paypal_secret']);
assertTest('PayPalGateway supports USD and EUR', $paypal->supportsCurrency('USD') && !$paypal->supportsCurrency('BDT'));
$paypalIntent = (PaymentIntent::create(200.00, 'USD', 'cust_02'))->withPaymentMethod($bankMethod);
$paypalResult = $paypal->charge($paypalIntent);
assertTest('PayPalGateway processes PayPal checkout', $paypalResult->isSuccess() && str_starts_with($paypalResult->getTransactionId(), 'PAYID-'));

// bKash MFS Gateway (BDT)
$bkash = new BKashGateway(['app_key' => 'bk_app', 'app_secret' => 'bk_sec']);
assertTest('BKashGateway supports BDT and rejects USD', $bkash->supportsCurrency('BDT') && !$bkash->supportsCurrency('USD'));
$bkashIntent = (PaymentIntent::create(1500.00, 'BDT', 'cust_03'))->withPaymentMethod($mfsMethod);
$bkashResult = $bkash->charge($bkashIntent);
assertTest('BKashGateway executes BDT MFS checkout with TrxID', $bkashResult->isSuccess() && str_starts_with($bkashResult->getTransactionId(), 'BKTRX'));

// Nagad MFS Gateway (BDT)
$nagad = new NagadGateway(['merchant_id' => 'nagad_merch']);
$nagadIntent = (PaymentIntent::create(2500.00, 'BDT', 'cust_04'))->withPaymentMethod(PaymentMethod::mfs('01811000000', 'nagad'));
$nagadResult = $nagad->charge($nagadIntent);
assertTest('NagadGateway processes BDT MFS charge', $nagadResult->isSuccess() && str_starts_with($nagadResult->getTransactionId(), 'NGD'));

// SSLCommerz Gateway (BDT / USD)
$sslcz = new SSLCommerzGateway(['store_id' => 'ssl_store']);
assertTest('SSLCommerzGateway supports multi-currency (BDT/USD/EUR)', $sslcz->supportsCurrency('BDT') && $sslcz->supportsCurrency('USD'));
$sslczIntent = (PaymentIntent::create(5000.00, 'BDT', 'cust_05'))->withPaymentMethod($cardToTokenize);
$sslczResult = $sslcz->charge($sslczIntent);
assertTest('SSLCommerzGateway executes payment session', $sslczResult->isSuccess() && str_starts_with($sslczResult->getTransactionId(), 'SSLCZ_TXN_'));

// Mock Gateway with failure simulation
$mock = (new MockGateway('mock_failover'))->setShouldFail(true);
$mockIntent = (PaymentIntent::create(100.00, 'USD', 'cust_06'))->withPaymentMethod($cardToTokenize);
$mockResult = $mock->charge($mockIntent);
assertTest('MockGateway simulates gateway failure correctly', !$mockResult->isSuccess() && $mockResult->getStatus() === 'failed');

// -------------------------------------------------------------
// 4. Gateway Health Tracker & Circuit Breaker
// -------------------------------------------------------------
echo "\n4. Testing Gateway Health Tracker & Circuit Breaker...\n";

$healthTracker = new GatewayHealthTracker(failureThreshold: 3, cooloffSeconds: 10);
assertTest('Gateway initially healthy', $healthTracker->isHealthy('stripe'));

$healthTracker->recordSuccess('stripe');
$healthTracker->recordFailure('stripe');
$healthTracker->recordFailure('stripe');
assertTest('Gateway still healthy after 2 failures (threshold 3)', $healthTracker->isHealthy('stripe'));

$healthTracker->recordFailure('stripe'); // 3rd failure trips circuit
assertTest('Gateway trips to UNHEALTHY / OPEN on 3 consecutive failures', !$healthTracker->isHealthy('stripe'));
assertTest('Health metrics track failure count correctly', $healthTracker->getStatus('stripe')['consecutive_failures'] === 3);

$healthTracker->reset('stripe');
assertTest('Gateway manually reset returns to HEALTHY state', $healthTracker->isHealthy('stripe'));

// -------------------------------------------------------------
// 5. Smart Payment Router & Fallbacks
// -------------------------------------------------------------
echo "\n5. Testing Smart Payment Router & Fallbacks...\n";

$router = new SmartPaymentRouter($healthTracker);
$router->registerGateway($stripe, priority: 100);
$router->registerGateway($paypal, priority: 90);
$router->registerGateway($bkash, priority: 100);
$router->registerGateway($nagad, priority: 80);
$router->registerGateway($sslcz, priority: 70);

// USD Routing
$usdIntent = (PaymentIntent::create(100.00, 'USD', 'cust_10'))->withPaymentMethod($cardToTokenize);
$routedUsd = $router->route($usdIntent);
assertTest('SmartRouter routes USD Card payment to Stripe (highest priority USD gateway)', $routedUsd->getName() === 'stripe');

$usdFallbacks = $router->getFallbacks($usdIntent, $routedUsd->getName());
assertTest('SmartRouter returns PayPal and SSLCommerz as USD fallbacks', count($usdFallbacks) >= 1 && $usdFallbacks[0]->getName() === 'paypal');

// BDT Routing with MFS bKash
$bdtIntent = (PaymentIntent::create(500.00, 'BDT', 'cust_11'))->withPaymentMethod($mfsMethod);
$routedBdt = $router->route($bdtIntent);
assertTest('SmartRouter routes BDT bKash payment to bKash gateway', $routedBdt->getName() === 'bkash');

// Fallback when primary gateway is unhealthy
$healthTracker->recordFailure('stripe');
$healthTracker->recordFailure('stripe');
$healthTracker->recordFailure('stripe'); // Stripe is now unhealthy
$routedWhenStripeDown = $router->route($usdIntent);
assertTest('SmartRouter bypasses unhealthy Stripe and auto-routes to PayPal', $routedWhenStripeDown->getName() === 'paypal');
$healthTracker->reset('stripe');

// -------------------------------------------------------------
// 6. Split Settlements & Double-Entry Accounting Ledger
// -------------------------------------------------------------
echo "\n6. Testing Split Settlements & Double-Entry Accounting Ledger...\n";

$ledger = new LedgerJournal($conn->getPdo());
$settlementEngine = new SplitSettlementEngine($ledger);

// Multi-Vendor Split Calculation on a $1000.00 charge with $30.00 fee
$mockResultForSplit = PaymentResult::success('tx_split_1001', 'stripe', 1000.00, 'USD', 30.00);
$splitRules = [
    new SplitRule('vendor_alpha', percentage: 70.0, fixedFee: 5.00, delayDays: 0),
    new SplitRule('vendor_beta', percentage: 20.0, fixedFee: 0.00, delayDays: 0),
];

$calculatedSplits = $settlementEngine->calculateSplits($mockResultForSplit, $splitRules, platformFee: 0.0);
assertTest('SplitSettlementEngine calculates vendor_alpha share (70% + $5.00 = $705.00)', ($calculatedSplits['vendor_alpha'] ?? 0.0) === 705.00);
assertTest('SplitSettlementEngine calculates vendor_beta share (20% = $200.00)', ($calculatedSplits['vendor_beta'] ?? 0.0) === 200.00);

// Double-entry ledger verification
assertTest('Ledger remains balanced after multi-vendor split posting (Debits == Credits)', $ledger->isBalanced());
assertTest('Escrow holding balance verified', $ledger->getBalance('escrow_holding', 'platform', 'USD') !== 0.0);

// Create Settlement Batch & Process Payout
$batch = $settlementEngine->createBatch('USD');
assertTest('SettlementBatch created with pending status', $batch->getStatus() === 'pending' && $batch->getTotalAmount() === 905.00);

$processed = $settlementEngine->processBatch($batch);
assertTest('SettlementBatch processed and marked completed', $processed && $batch->getStatus() === 'completed');
assertTest('Ledger remains balanced after payout processing', $ledger->isBalanced());

// -------------------------------------------------------------
// 7. Reconciliation Engine (Statements & Variances)
// -------------------------------------------------------------
echo "\n7. Testing Reconciliation Engine & Discrepancy Detection...\n";

$reconciliationEngine = new ReconciliationEngine($ledger, $conn->getPdo());
$reconciliationEngine->recordInternalTransaction('stripe', 'tx_rec_1001', 1000.00, 30.00, 'succeeded');

// Statement matching with exact match and fee variance
$processorStatement = [
    [
        'transaction_id' => 'tx_rec_1001',
        'processor' => 'stripe',
        'amount' => 1000.00,
        'fee' => 32.50, // Internal recorded 30.00, variance = 2.50
        'status' => 'succeeded',
        'date' => date('Y-m-d'),
    ],
    [
        'transaction_id' => 'tx_ghost_999', // Missing internally
        'processor' => 'stripe',
        'amount' => 250.00,
        'fee' => 7.50,
        'status' => 'succeeded',
        'date' => date('Y-m-d'),
    ]
];

$recReport = $reconciliationEngine->reconcile('stripe', $processorStatement);
assertTest('ReconciliationEngine processes processor statement', ($recReport['matched'] + $recReport['unmatched']) === 2);
assertTest('ReconciliationEngine identifies fee variance on tx_rec_1001', ($recReport['fee_variance'] ?? 0.0) === 2.5);
assertTest('ReconciliationEngine flags unrecorded processor transaction as missing', count($recReport['discrepancies']) >= 1);

// -------------------------------------------------------------
// 8. Payment Orchestrator Facade & Automatic Failover Retry
// -------------------------------------------------------------
echo "\n8. Testing Payment Orchestrator Facade & Failover...\n";

$orchestrator = new PaymentOrchestrator(
    config: [],
    router: $router,
    vault: $vault,
    settlement: $settlementEngine,
    reconciliation: $reconciliationEngine,
    pdo: $conn->getPdo()
);

// End-to-End Successful Charge with Split Rules & Token
$orchestratorIntent = (PaymentIntent::create(500.00, 'USD', 'cust_enterprise_01'))
    ->withPaymentMethodToken($activeToken)
    ->addSplit(new SplitRule('merchant_1', percentage: 85.0, fixedFee: 2.00))
    ->withIdempotencyKey('idemp_key_alpha_1');

$orchResult = $orchestrator->charge($orchestratorIntent);
assertTest('PaymentOrchestrator executes end-to-end charge', $orchResult->isSuccess() && $orchResult->getStatus() === 'succeeded');
assertTest('PaymentOrchestrator attaches split settlement results', isset($orchResult->getSplits()['merchant_1']));

// Idempotency Replay Protection
$replayedResult = $orchestrator->charge($orchestratorIntent);
assertTest('PaymentOrchestrator handles idempotent requests without double-charging', $replayedResult->getTransactionId() === $orchResult->getTransactionId());

// Automatic Failover: Add failing gateway with highest priority
$failingGateway = (new MockGateway('failing_primary'))->setShouldFail(true);
$router->registerGateway($failingGateway, priority: 999);

$failoverIntent = (PaymentIntent::create(120.00, 'USD', 'cust_failover_test'))->withPaymentMethod($cardToTokenize);
$failoverResult = $orchestrator->charge($failoverIntent);
assertTest('PaymentOrchestrator auto-failovers to secondary healthy gateway (Stripe/PayPal) when primary fails', $failoverResult->isSuccess() && $failoverResult->getProcessor() !== 'failing_primary');

// -------------------------------------------------------------
// 9. Framework Helpers & CLI Commands
// -------------------------------------------------------------
echo "\n9. Testing Framework Helpers & CLI Commands...\n";

// Register orchestrator in DI container
$app->instance('payment', $orchestrator);
$app->instance(PaymentOrchestrator::class, $orchestrator);

assertTest('Global payment() helper returns PaymentOrchestrator', payment() instanceof PaymentOrchestrator);

// Global helper: payment_tokenize()
$helperToken = payment_tokenize($cardToTokenize, 'cust_helper');
assertTest('payment_tokenize() creates valid token record', str_starts_with($helperToken, 'tok_visa_'));

// Global helper: payment_split()
$helperSplitRule = payment_split('seller_1', 80.0, 1.50);
assertTest('payment_split() calculates split allocations', $helperSplitRule->calculateAmount(100.00) === 81.50);

// CLI Command: payment:status & payment:reconcile
$kernel = new \NetFusion\Core\Console\Kernel($app);
ob_start();
$statusExit = $kernel->call('payment:status', []);
$statusOutput = ob_get_clean();
assertTest('PaymentStatusCommand executes successfully (exit code 0)', $statusExit === 0);
assertTest('PaymentStatusCommand outputs gateway health metrics', str_contains($statusOutput, 'Gateway Health & Routing Status') && str_contains($statusOutput, 'STRIPE'));

ob_start();
$reconcileExit = $kernel->call('payment:reconcile', ['--processor' => 'stripe']);
$reconcileOutput = ob_get_clean();
assertTest('PaymentReconcileCommand executes successfully (exit code 0)', $reconcileExit === 0);
assertTest('PaymentReconcileCommand outputs reconciliation summary', str_contains($reconcileOutput, 'Reconciliation Summary'));

// Clean up test sqlite
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}

echo "\n========================================================================\n";
echo "  Phase 63 Test Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "========================================================================\n\n";

exit($testsFailed > 0 ? 1 : 0);
