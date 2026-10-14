<?php

/**
 * DotNetBD Enterprise Framework - Phase 58 Comprehensive Test Suite
 * Enterprise Zero-Trust Service Mesh, Sidecar Proxy & Distributed mTLS Policy Gateway
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use NetFusion\Core\ServiceMesh\Security\SpiffeId;
use NetFusion\Core\ServiceMesh\Security\CertificateManager;
use NetFusion\Core\ServiceMesh\Security\SecurityContext;
use NetFusion\Core\ServiceMesh\Security\MeshAuthorizationPolicy;
use NetFusion\Core\ServiceMesh\Routing\TrafficRoute;
use NetFusion\Core\ServiceMesh\Routing\TrafficSplitter;
use NetFusion\Core\ServiceMesh\Routing\FaultInjector;
use NetFusion\Core\ServiceMesh\Routing\RetryPolicy;
use NetFusion\Core\ServiceMesh\Health\ServiceInstance;
use NetFusion\Core\ServiceMesh\LoadBalancer\RoundRobinLoadBalancer;
use NetFusion\Core\ServiceMesh\LoadBalancer\LeastConnectionLoadBalancer;
use NetFusion\Core\ServiceMesh\LoadBalancer\ConsistentHashLoadBalancer;
use NetFusion\Core\ServiceMesh\Resilience\OutlierDetector;
use NetFusion\Core\ServiceMesh\Resilience\MeshCircuitBreaker;
use NetFusion\Core\ServiceMesh\Resilience\MeshRateLimiter;
use NetFusion\Core\ServiceMesh\Proxy\SidecarProxy;
use NetFusion\Core\ServiceMesh\Proxy\MeshRequestContext;
use NetFusion\Core\ServiceMesh\Proxy\MeshResponse;
use NetFusion\Core\ServiceMesh\Store\ArrayMeshStore;
use NetFusion\Core\ServiceMesh\Store\DatabaseMeshStore;
use NetFusion\Core\ServiceMesh\ServiceMeshManager;
use NetFusion\Core\Console\Kernel;

$pass = 0;
$fail = 0;

function assertTest(bool $condition, string $testName): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  [PASS] {$testName}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$testName}\n";
    }
}

echo "================================================================================\n";
echo "           PHASE 58: ZERO-TRUST SERVICE MESH & SIDECAR PROXY TEST SUITE         \n";
echo "================================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. SPIFFE Identity & Verification
// -----------------------------------------------------------------------------
echo "[1] Testing SPIFFE Identity & Pattern Matching...\n";

$spiffe1 = SpiffeId::of('billing-service', 'prod', 'cluster.local');
assertTest($spiffe1->toString() === 'spiffe://cluster.local/ns/prod/sa/billing-service', "SpiffeId string format matches RFC standard");
assertTest($spiffe1->getTrustDomain() === 'cluster.local', "SpiffeId trust domain matches");
assertTest($spiffe1->getNamespace() === 'prod', "SpiffeId namespace matches");
assertTest($spiffe1->getServiceAccount() === 'billing-service', "SpiffeId service account matches");

$spiffeParsed = SpiffeId::fromString('spiffe://mesh.corp.internal/ns/finance/sa/payment-api');
assertTest($spiffeParsed->getTrustDomain() === 'mesh.corp.internal', "SpiffeId parsed custom trust domain");
assertTest($spiffeParsed->getNamespace() === 'finance', "SpiffeId parsed namespace");
assertTest($spiffeParsed->getServiceAccount() === 'payment-api', "SpiffeId parsed service account");

assertTest($spiffe1->matches('spiffe://cluster.local/ns/prod/*'), "SpiffeId matches wildcard pattern");
assertTest($spiffe1->matches('*'), "SpiffeId matches global wildcard");
assertTest(!$spiffe1->matches('spiffe://cluster.local/ns/dev/*'), "SpiffeId rejects non-matching namespace pattern");

// -----------------------------------------------------------------------------
// 2. Certificate Authority & mTLS
// -----------------------------------------------------------------------------
echo "\n[2] Testing Certificate Manager & mTLS Keypair Issuance...\n";

$certManager = new CertificateManager('cluster.local');
assertTest(!empty($certManager->getRootCaPem()), "Root CA certificate initialized");

$issued = $certManager->issueCertificate($spiffe1, 3600);
assertTest(!empty($issued['cert']) && !empty($issued['key']), "Issued leaf X.509 certificate and private key");
assertTest($issued['spiffe_id'] === $spiffe1->toString(), "Certificate metadata references SPIFFE ID");

$verified = $certManager->verifyCertificate($issued['cert']);
assertTest($verified['valid'] === true, "Issued certificate passes validation");
assertTest($verified['common_name'] === 'billing-service', "Certificate common name extracted correctly");

$rotated = $certManager->rotateCertificate($spiffe1);
assertTest(!empty($rotated['cert']) && $rotated['expires_at'] >= time(), "Rotated certificate successfully");

// -----------------------------------------------------------------------------
// 3. Zero-Trust Authorization Policies & SecurityContext
// -----------------------------------------------------------------------------
echo "\n[3] Testing Zero-Trust Authorization Policies & Security Context...\n";

$secCtx = SecurityContext::authenticated($spiffe1, '10.0.0.5', ['role' => 'payment_admin']);
assertTest($secCtx->isMtlsAuthenticated(), "SecurityContext reports mTLS authenticated");
assertTest($secCtx->getPeerSpiffeId()->getServiceAccount() === 'billing-service', "SecurityContext peer identity matches");

$policyAllow = MeshAuthorizationPolicy::allow('allow_orders_to_billing')
    ->from('spiffe://cluster.local/ns/prod/*')
    ->to('billing-service')
    ->methods('POST', 'GET')
    ->paths('/api/v1/charges/*')
    ->requireClaim('role', 'payment_admin');

assertTest($policyAllow->matches($secCtx, 'billing-service', 'POST', '/api/v1/charges/101', []), "Policy allows matching valid request");
assertTest(!$policyAllow->matches($secCtx, 'billing-service', 'DELETE', '/api/v1/charges/101', []), "Policy rejects disallowed HTTP method");
assertTest(!$policyAllow->matches($secCtx, 'user-service', 'POST', '/api/v1/charges/101', []), "Policy rejects mismatched destination service");

$unauthCtx = SecurityContext::anonymous('10.0.0.9');
assertTest(!$policyAllow->matches($unauthCtx, 'billing-service', 'POST', '/api/v1/charges/101', []), "Policy rejects anonymous caller");

// -----------------------------------------------------------------------------
// 4. Traffic Routing, Canary Splitting, Fault Injection & Retries
// -----------------------------------------------------------------------------
echo "\n[4] Testing Traffic Routing, Canary Splitting & Chaos Testing...\n";

$splitter = TrafficSplitter::canary('v1', 'v2', 20)
    ->matchHeader('X-Canary-Beta', 'true', 'v2')
    ->shadowTo('v2');

$reqCtxV1 = new MeshRequestContext('order-service', '/orders');
$reqCtxBeta = (new MeshRequestContext('order-service', '/orders'))->withHeader('X-Canary-Beta', 'true');

assertTest($splitter->resolveSubset($reqCtxBeta) === 'v2', "TrafficSplitter routes header match directly to v2");
assertTest($splitter->getShadowSubset() === 'v2', "TrafficSplitter configured shadow subset");

// Test Fault Injector
$fault = FaultInjector::abort(503, 100.0, 'Simulated chaos failure');
$faultTriggered = false;
try {
    $fault->execute();
} catch (\NetFusion\Core\ServiceMesh\Exceptions\FaultInjectedException $e) {
    $faultTriggered = true;
    assertTest($e->getHttpStatusCode() === 503, "FaultInjector throws FaultInjectedException with status 503");
}
assertTest($faultTriggered, "FaultInjector abort executed");

// Test Retry Policy
$retry = new RetryPolicy(3, 10, 50, 2.0, false, [503, 504]);
assertTest($retry->shouldRetry(0, 503), "RetryPolicy allows retry on 503 attempt 0");
assertTest($retry->shouldRetry(2, 504), "RetryPolicy allows retry on 504 attempt 2");
assertTest(!$retry->shouldRetry(3, 503), "RetryPolicy halts at maxRetries = 3");
assertTest(!$retry->shouldRetry(1, 400), "RetryPolicy does not retry 400 Bad Request");
assertTest($retry->getBackoffMs(1) === 20, "RetryPolicy calculates exponential backoff");

// -----------------------------------------------------------------------------
// 5. Load Balancing, Health Discovery & Outlier Detection
// -----------------------------------------------------------------------------
echo "\n[5] Testing Load Balancers, Health State & Outlier Detection...\n";

$inst1 = new ServiceInstance('inst-1', 'inventory-service', '10.0.1.1', 8080, 'v1');
$inst2 = new ServiceInstance('inst-2', 'inventory-service', '10.0.1.2', 8080, 'v1');
$inst3 = new ServiceInstance('inst-3', 'inventory-service', '10.0.1.3', 8080, 'v2');

$lbRoundRobin = new RoundRobinLoadBalancer();
$chosen1 = $lbRoundRobin->choose([$inst1, $inst2]);
$chosen2 = $lbRoundRobin->choose([$inst1, $inst2]);
assertTest($chosen1->getId() === 'inst-1' && $chosen2->getId() === 'inst-2', "RoundRobinLoadBalancer alternates instances");

$inst1->incrementActiveConnections();
$inst1->incrementActiveConnections();
$lbLeastConn = new LeastConnectionLoadBalancer();
$chosenLeast = $lbLeastConn->choose([$inst1, $inst2]);
assertTest($chosenLeast->getId() === 'inst-2', "LeastConnectionLoadBalancer selects instance with lower active connections");

$lbHash = new ConsistentHashLoadBalancer('X-User-Id');
$ctxUserA = (new MeshRequestContext('inventory-service', '/items'))->withHeader('X-User-Id', 'user_alpha');
$ctxUserB = (new MeshRequestContext('inventory-service', '/items'))->withHeader('X-User-Id', 'user_alpha');
assertTest($lbHash->choose([$inst1, $inst2], $ctxUserA)->getId() === $lbHash->choose([$inst1, $inst2], $ctxUserB)->getId(), "ConsistentHashLoadBalancer returns deterministic instance for same key");

// Test Outlier Detection (Ejection / Quarantine)
$outlierDetector = new OutlierDetector(consecutive5xxThreshold: 3, baseEjectionDurationSeconds: 0.1);
$outlierDetector->recordResult($inst1, 500, 10.0);
$outlierDetector->recordResult($inst1, 502, 12.0);
assertTest($inst1->isAvailable(), "Instance remains available before reaching consecutive error threshold");
$outlierDetector->recordResult($inst1, 503, 15.0);
assertTest(!$inst1->isAvailable(), "OutlierDetector ejects faulty instance upon reaching 3 consecutive 5xx errors");
assertTest($inst1->getStatus() === ServiceInstance::STATUS_EJECTED, "Instance status transitioned to EJECTED");

usleep(150000); // 150ms > 100ms ejection duration
assertTest($inst1->isAvailable(), "Instance auto-restores to HEALTHY after ejection duration expires");

// -----------------------------------------------------------------------------
// 6. Circuit Breaker & Rate Limiter
// -----------------------------------------------------------------------------
echo "\n[6] Testing Circuit Breaker & Rate Limiter...\n";

$cb = new MeshCircuitBreaker('payment-gateway', failureThreshold: 2, recoveryTimeoutSeconds: 0.1, halfOpenSuccessThreshold: 2);
assertTest($cb->getState() === MeshCircuitBreaker::STATE_CLOSED, "Circuit breaker starts in CLOSED state");
assertTest($cb->allowRequest(), "CLOSED circuit allows requests");

$cb->recordFailure();
$cb->recordFailure();
assertTest($cb->getState() === MeshCircuitBreaker::STATE_OPEN, "Circuit breaker trips to OPEN upon reaching failure threshold");
assertTest(!$cb->allowRequest(), "OPEN circuit blocks requests");

usleep(150000); // Wait 150ms > 100ms recovery timeout
assertTest($cb->allowRequest(), "Circuit allows test request in HALF_OPEN state");
assertTest($cb->getState() === MeshCircuitBreaker::STATE_HALF_OPEN, "Circuit transitioned to HALF_OPEN");

$cb->recordSuccess();
$cb->recordSuccess();
assertTest($cb->getState() === MeshCircuitBreaker::STATE_CLOSED, "Circuit breaker resets to CLOSED after consecutive half-open successes");

// Test Rate Limiter
$rateLimiter = new MeshRateLimiter(ratePerSecond: 10.0, capacity: 2);
assertTest($rateLimiter->consume('key1'), "RateLimiter consumes token 1");
assertTest($rateLimiter->consume('key1'), "RateLimiter consumes token 2");
assertTest(!$rateLimiter->consume('key1'), "RateLimiter blocks when bucket empty");

// -----------------------------------------------------------------------------
// 7. Sidecar Proxy Inbound & Outbound Execution
// -----------------------------------------------------------------------------
echo "\n[7] Testing Sidecar Proxy Inbound & Outbound Interception...\n";

$meshMgr = new ServiceMeshManager('cluster.local');
$proxy = $meshMgr->proxy('order-service');

// Register microservices
$meshMgr->registerService(new ServiceInstance('order-1', 'order-service', '127.0.0.1', 8001, 'v1'));
$meshMgr->registerService(new ServiceInstance('payment-1', 'payment-service', '127.0.0.1', 8002, 'v1'));

// Register Inbound handler on payment-service sidecar
$paymentProxy = $meshMgr->proxy('payment-service');
$paymentProxy->setInboundHandler(function (MeshRequestContext $ctx) {
    return ['status' => 'processed', 'charge_id' => 'ch_999'];
});

// Configure Route with Splitter
$route = TrafficRoute::to('payment-service', 'payment_route')
    ->prefix('/charges')
    ->retry(new RetryPolicy(2, 5));

$meshMgr->addRoute($route);

// Outbound call from order-service -> payment-service
$proxy->setTransportHandler(function (ServiceInstance $inst, MeshRequestContext $ctx) use ($paymentProxy) {
    return $paymentProxy->handleInbound($ctx);
});

$callCtx = new MeshRequestContext('payment-service', '/charges/process', 'POST', ['Content-Type' => 'application/json'], ['amount' => 50.0]);
$response = $proxy->handleOutbound($callCtx);

assertTest($response->isSuccess(), "SidecarProxy successfully routed outbound call to payment-service");
assertTest($response->json()['charge_id'] === 'ch_999', "Response body deserialized correctly");
assertTest($response->getInstanceId() === 'payment-1', "Response metadata contains instance ID");

// Test Authorization Policy Enforcement in Inbound Proxy
$strictPolicy = MeshAuthorizationPolicy::allow('allow_only_admin_sa')
    ->from('spiffe://cluster.local/ns/prod/sa/admin-service')
    ->to('payment-service');

$meshMgr->addPolicy($strictPolicy);

// Now unauthorized call from order-service should return 403 Forbidden
$unauthResponse = $proxy->handleOutbound($callCtx);
assertTest($unauthResponse->getStatusCode() === 403, "Inbound proxy enforced Zero-Trust policy and returned 403 Forbidden");

// -----------------------------------------------------------------------------
// 8. Stores (ArrayMeshStore & SQLite DatabaseMeshStore)
// -----------------------------------------------------------------------------
echo "\n[8] Testing Array & Database Mesh Stores...\n";

$arrayStore = new ArrayMeshStore();
$arrayStore->saveServiceInstance(new ServiceInstance('arr-1', 'catalog', '10.0.0.1', 9000));
assertTest(count($arrayStore->getServiceInstances('catalog')) === 1, "ArrayMeshStore saved and retrieved instance");

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_21_000038_create_service_mesh_tables.php';
$migration->up($pdo);

$dbStore = new DatabaseMeshStore($pdo);
$dbStore->saveServiceInstance(new ServiceInstance('db-inst-1', 'catalog', '10.0.0.2', 9001, 'v2'));
$dbInstances = $dbStore->getServiceInstances('catalog');
assertTest(count($dbInstances) === 1, "DatabaseMeshStore persisted and retrieved instance from SQLite");
assertTest($dbInstances[0]->getSubset() === 'v2', "Database instance subset is v2");

$dbStore->saveRoute(TrafficRoute::to('catalog', 'catalog_route')->prefix('/items'));
assertTest(count($dbStore->getRoutes()) === 1, "DatabaseMeshStore saved route config");

$dbStore->savePolicy(MeshAuthorizationPolicy::allow('db_policy')->to('catalog'));
assertTest(count($dbStore->getPolicies()) === 1, "DatabaseMeshStore saved policy");

$dbStore->saveCertificate('spiffe://cluster.local/ns/default/sa/catalog', [
    'cert' => 'CERT_PEM',
    'key' => 'KEY_PEM',
    'ca' => 'CA_PEM',
    'expires_at' => time() + 3600,
]);
$loadedCert = $dbStore->getCertificate('spiffe://cluster.local/ns/default/sa/catalog');
assertTest($loadedCert['cert'] === 'CERT_PEM', "DatabaseMeshStore persisted and loaded mTLS certificate");

$dbStore->recordMetric('mesh.requests.total', 1250.0, ['service' => 'catalog']);
$metricsCnt = (int) $pdo->query("SELECT COUNT(*) FROM nf_mesh_metrics")->fetchColumn();
assertTest($metricsCnt === 1, "DatabaseMeshStore recorded metric into nf_mesh_metrics");

// -----------------------------------------------------------------------------
// 9. Master ServiceMeshManager Facade & Global Helpers
// -----------------------------------------------------------------------------
echo "\n[9] Testing ServiceMeshManager & Global Helpers...\n";

$masterMesh = new ServiceMeshManager('cluster.local', $dbStore);
$stats = $masterMesh->getStats();
assertTest($stats['total_services'] === 1, "ServiceMeshManager reports 1 service in store");
assertTest($stats['trust_domain'] === 'cluster.local', "ServiceMeshManager reports correct trust domain");

assertTest(function_exists('mesh'), "Global helper mesh() exists");
assertTest(function_exists('mesh_proxy'), "Global helper mesh_proxy() exists");
assertTest(function_exists('mesh_route'), "Global helper mesh_route() exists");
assertTest(function_exists('spiffe_id'), "Global helper spiffe_id() exists");

$helperSpiffe = spiffe_id('inventory-api', 'prod');
assertTest($helperSpiffe->toString() === 'spiffe://cluster.local/ns/prod/sa/inventory-api', "spiffe_id() helper creates valid SpiffeId");

// -----------------------------------------------------------------------------
// 10. CLI Console Commands
// -----------------------------------------------------------------------------
echo "\n[10] Testing Console Commands Registration & Output...\n";

$kernel = app()->make(Kernel::class);
$commands = $kernel->getCommands();

assertTest(isset($commands['mesh:services']), "Command 'mesh:services' is registered in Console Kernel");
assertTest(isset($commands['mesh:routes']), "Command 'mesh:routes' is registered in Console Kernel");
assertTest(isset($commands['mesh:certs']), "Command 'mesh:certs' is registered in Console Kernel");
assertTest(isset($commands['mesh:stats']), "Command 'mesh:stats' is registered in Console Kernel");

ob_start();
$exitCode = $kernel->handle(['netfusion', 'mesh:stats']);
$output = ob_get_clean();
assertTest($exitCode === 0, "Command 'mesh:stats' executed successfully with exit code 0");
assertTest(str_contains($output, 'Zero-Trust Service Mesh & Sidecar Proxy Runtime Topology'), "Command 'mesh:stats' output header matched");

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n================================================================================\n";
echo "           PHASE 58 TEST SUMMARY: {$pass} PASSED, {$fail} FAILED\n";
echo "================================================================================\n";

if ($fail > 0) {
    exit(1);
}
