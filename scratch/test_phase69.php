<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Phase 69 Unit Test Suite
 |--------------------------------------------------------------------------
 |
 | Covers:
 |   69A: SPIFFE/SPIRE Identity & Workload Attestation
 |   69B: mTLS Dynamic Certificate & Handshake Engine
 |   69C: L7 Zero-Trust Traffic Policy & Service Authorization
 |   69D: Service Mesh Topology & Sidecar Proxy Orchestrator
 |
 | Run: D:\SERVER\bin\php\php8.2.29\php.exe scratch/test_phase69.php
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Autoload
spl_autoload_register(function (string $class): void {
    $map = [
        'NetFusion\\Core\\' => BASE_PATH . '/core/',
    ];
    foreach ($map as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $base . $rel . '.php';
            if (file_exists($file)) { require_once $file; return; }
        }
    }
    $vendor = BASE_PATH . '/vendor/autoload.php';
    if (file_exists($vendor)) require_once $vendor;
});

require_once BASE_PATH . '/core/Support/helpers.php';

// Test counters
$passed = 0;
$failed = 0;
$errors = [];

function assertTest(bool $condition, string $label): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo "  ✓ {$label}\n";
        $passed++;
    } else {
        echo "  ✗ {$label}\n";
        $failed++;
        $errors[] = $label;
    }
}

function section(string $title): void {
    echo "\n═══ {$title} ═══\n";
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║ NetFusion Phase 69 — Zero-Trust Service Mesh & SPIFFE/mTLS  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;
use NetFusion\Core\ServiceMesh\Spiffe\TrustBundle;
use NetFusion\Core\ServiceMesh\Spiffe\X509Svid;
use NetFusion\Core\ServiceMesh\Spiffe\JwtSvid;
use NetFusion\Core\ServiceMesh\Spiffe\WorkloadAttestor;

use NetFusion\Core\ServiceMesh\Tls\CertificateAuthority;
use NetFusion\Core\ServiceMesh\Tls\CertificatePair;
use NetFusion\Core\ServiceMesh\Tls\CertificateRotator;
use NetFusion\Core\ServiceMesh\Tls\MtlsContext;
use NetFusion\Core\ServiceMesh\Tls\SecureSocket;

use NetFusion\Core\ServiceMesh\Policy\ServiceIdentity;
use NetFusion\Core\ServiceMesh\Policy\MeshRule;
use NetFusion\Core\ServiceMesh\Policy\AccessDecision;
use NetFusion\Core\ServiceMesh\Policy\MeshPolicy;
use NetFusion\Core\ServiceMesh\Policy\PolicyEngine;

use NetFusion\Core\ServiceMesh\Mesh\MeshNode;
use NetFusion\Core\ServiceMesh\Mesh\MeshTopology;
use NetFusion\Core\ServiceMesh\Mesh\SidecarProxy;
use NetFusion\Core\ServiceMesh\Mesh\ServiceMeshManager;

// =============================================================================
// MODULE 69A: SPIFFE/SPIRE Identity & Workload Attestation
// =============================================================================

section('69A-1: SpiffeId — Parser & Validator');

$id1 = new SpiffeId('spiffe://cluster.local/ns/prod/sa/payment-service');
assertTest($id1->getTrustDomain() === 'cluster.local', 'SpiffeId parses trust domain accurately');
assertTest($id1->getPath() === '/ns/prod/sa/payment-service', 'SpiffeId parses workload path');
assertTest($id1->getWorkloadName() === 'payment-service', 'SpiffeId extracts workload name');
assertTest($id1->getNamespace() === 'prod', 'SpiffeId extracts namespace correctly');
assertTest((string) $id1 === 'spiffe://cluster.local/ns/prod/sa/payment-service', 'SpiffeId casts to canonical string');

$id2 = SpiffeId::of('mesh.internal', 'billing');
assertTest((string) $id2 === 'spiffe://mesh.internal/billing', 'SpiffeId::of constructs clean URI');

assertTest(SpiffeId::isValid('spiffe://domain.com/workload'), 'SpiffeId::isValid returns true for valid URI');
assertTest(!SpiffeId::isValid('http://domain.com/workload'), 'SpiffeId::isValid returns false for non-spiffe scheme');
assertTest(!SpiffeId::isValid('spiffe:///empty-domain'), 'SpiffeId::isValid rejects empty trust domain');

// Matching
assertTest($id1->matches('spiffe://cluster.local/ns/prod/*'), 'SpiffeId matches wildcard path pattern');
assertTest($id1->matches('spiffe://cluster.local/*'), 'SpiffeId matches global trust domain wildcard');
assertTest(!$id1->matches('spiffe://other.domain/*'), 'SpiffeId rejects non-matching trust domain wildcard');
assertTest($id1->matches((string) $id1), 'SpiffeId matches exact URI');

section('69A-2: TrustBundle — Multi-Domain CA Bundles');

$ca1 = CertificateAuthority::create('cluster.local');
$ca2 = CertificateAuthority::create('partner.mesh');

$bundle = new TrustBundle();
$bundle->addCertificate('cluster.local', $ca1->getCaCertificatePem());
$bundle->addCertificate('partner.mesh', $ca2->getCaCertificatePem());

assertTest($bundle->hasTrustDomain('cluster.local'), 'TrustBundle recognizes primary trust domain');
assertTest($bundle->hasTrustDomain('partner.mesh'), 'TrustBundle recognizes federated trust domain');
assertTest(!$bundle->hasTrustDomain('unknown.domain'), 'TrustBundle returns false for unregistered domain');
assertTest(count($bundle->getCertificates('cluster.local')) === 1, 'TrustBundle retrieves CA certificate list');

section('69A-3: X509Svid — SAN URI & Expiry Validation');

$svidCert = $ca1->issueWorkloadCertificate(
    'spiffe://cluster.local/ns/default/sa/order-service',
    3600,
    ['order.default.svc']
);

$x509 = new X509Svid($svidCert->getCertificatePem(), $svidCert->getPrivateKeyPem());
assertTest((string) $x509->getSpiffeId() === 'spiffe://cluster.local/ns/default/sa/order-service', 'X509Svid extracts SPIFFE ID from SAN URI extension');
assertTest(!$x509->isExpired(), 'X509Svid detects valid non-expired certificate');
assertTest($x509->isValidAt(time() + 1800), 'X509Svid is valid at halfway lifetime');
assertTest(!$x509->isValidAt($x509->getValidTo() + 10), 'X509Svid rejects timestamp beyond expiration');
assertTest(!empty($x509->getSerialNumber()), 'X509Svid extracts serial number');

section('69A-4: JwtSvid — Token Signing & Verification');

$secretKey = 'super-secret-service-mesh-signing-key-123!';
$jwtToken = JwtSvid::create(
    new SpiffeId('spiffe://cluster.local/ns/prod/sa/frontend'),
    'backend-api',
    300,
    $secretKey,
    'HS256',
    ['custom_role' => 'client']
);

$parsedJwt = JwtSvid::parseAndVerify($jwtToken, $secretKey, 'backend-api', 'HS256');
assertTest((string) $parsedJwt->getSpiffeId() === 'spiffe://cluster.local/ns/prod/sa/frontend', 'JwtSvid parses subject SPIFFE ID');
assertTest(in_array('backend-api', $parsedJwt->getAudience(), true), 'JwtSvid validates expected audience');
assertTest($parsedJwt->getClaims()['custom_role'] === 'client', 'JwtSvid preserves custom claims');

// Audience mismatch throws
$threwAudience = false;
try {
    JwtSvid::parseAndVerify($jwtToken, $secretKey, 'wrong-audience', 'HS256');
} catch (\RuntimeException) {
    $threwAudience = true;
}
assertTest($threwAudience, 'JwtSvid rejects invalid audience with RuntimeException');

// Bad signature throws
$threwSig = false;
try {
    JwtSvid::parseAndVerify($jwtToken, 'wrong-key!', 'backend-api', 'HS256');
} catch (\RuntimeException) {
    $threwSig = true;
}
assertTest($threwSig, 'JwtSvid rejects signature tampering with RuntimeException');

section('69A-5: WorkloadAttestor — Selector-Based Attestation');

$attestor = new WorkloadAttestor('cluster.local');
$attestor->registerWorkload('payment-worker', [
    'service' => 'payment',
    'namespace' => 'finance',
    'env' => 'production'
], 'spiffe://cluster.local/ns/finance/sa/payment-worker');

$attestedId = $attestor->attest([
    'service' => 'payment',
    'namespace' => 'finance',
    'env' => 'production',
    'pid' => 1234
]);
assertTest((string) $attestedId === 'spiffe://cluster.local/ns/finance/sa/payment-worker', 'WorkloadAttestor matches selectors to registered SPIFFE ID');

// Auto attestation fallback
$autoId = $attestor->attest(['service' => 'inventory', 'namespace' => 'stock']);
assertTest((string) $autoId === 'spiffe://cluster.local/ns/stock/sa/inventory', 'WorkloadAttestor auto-attests unmapped service using standard path');

// =============================================================================
// MODULE 69B: mTLS Dynamic Certificate & Handshake Engine
// =============================================================================

section('69B-1: CertificateAuthority — Root CA & Leaf Issuance');

$meshCa = CertificateAuthority::create('mesh.netfusion');
assertTest($meshCa->getTrustDomain() === 'mesh.netfusion', 'CertificateAuthority initializes with configured trust domain');
assertTest(!empty($meshCa->getCaCertificatePem()), 'CertificateAuthority generates root CA self-signed certificate');

$leafPair = $meshCa->issueWorkloadCertificate(
    'spiffe://mesh.netfusion/ns/core/sa/auth-service',
    7200,
    ['auth.mesh.netfusion', 'auth-service']
);
assertTest($leafPair instanceof CertificatePair, 'CertificateAuthority issues CertificatePair');
assertTest(!empty($leafPair->getCertificatePem()) && !empty($leafPair->getPrivateKeyPem()), 'CertificatePair contains both cert and private key PEM');
assertTest((string) $leafPair->getSpiffeId() === 'spiffe://mesh.netfusion/ns/core/sa/auth-service', 'CertificatePair holds correct SPIFFE ID');

[$cFile, $kFile] = $leafPair->writeToTempFiles();
assertTest(file_exists($cFile) && file_exists($kFile), 'CertificatePair writes temporary files cleanly');
@unlink($cFile);
@unlink($kFile);

section('69B-2: CertificateRotator — Lifecycle & Renewal');

$rotator = new CertificateRotator($meshCa, rotationGraceSeconds: 600, defaultTtl: 3600);
$rotator->register('auth-service', $leafPair);

assertTest($rotator->getActiveCount() === 1, 'CertificateRotator tracks active certificate count');
$fetched = $rotator->getCertificate('auth-service');
assertTest($fetched->getCertificatePem() === $leafPair->getCertificatePem(), 'CertificateRotator retrieves active certificate');

// Rotating when not expired returns existing
$checked = $rotator->checkAndRotate('auth-service');
assertTest($checked->getCertificatePem() === $leafPair->getCertificatePem(), 'CertificateRotator preserves valid unexpired certificate');

// Force rotation by creating expired pair
$expiredPair = new CertificatePair(
    $leafPair->getCertificatePem(),
    $leafPair->getPrivateKeyPem(),
    $leafPair->getSpiffeId(),
    time() - 100 // Expired
);
$rotator->register('billing-service', $expiredPair);
$rotated = $rotator->checkAndRotate('billing-service');
assertTest($rotated->getExpiresAt() > time(), 'CertificateRotator automatically rotates expired certificate with new SVID');
assertTest($rotator->getRotationCount() === 1, 'CertificateRotator increments rotation count');

section('69B-3: MtlsContext — Client & Server Stream Options');

$clientCtx = MtlsContext::forClient($leafPair, $meshCa->getCaCertificatePem());
$cOpts = $clientCtx->getSslOptions();
assertTest($cOpts['verify_peer'] === true, 'MtlsContext enables verify_peer for client');
assertTest(!empty($cOpts['cafile']) && file_exists($cOpts['cafile']), 'MtlsContext configures cafile for client');
assertTest(!empty($cOpts['local_cert']) && file_exists($cOpts['local_cert']), 'MtlsContext configures local_cert for client');

$serverCtx = MtlsContext::forServer($leafPair, $meshCa->getCaCertificatePem(), requireClientCert: true);
$sOpts = $serverCtx->getSslOptions();
assertTest($sOpts['fail_if_no_peer_cert'] === true, 'MtlsContext enforces fail_if_no_peer_cert for server mutual authentication');

$streamResource = $clientCtx->toStreamContext();
assertTest(is_resource($streamResource), 'MtlsContext creates valid PHP stream context resource');

section('69B-4: SecureSocket — Simulation & Handshake');

$socket = new SecureSocket('127.0.0.1', 8443, $clientCtx, simulate: true);
$socket->connect();
assertTest($socket->isConnected(), 'SecureSocket connects successfully in simulate mode');

$written = $socket->write("GET /health HTTP/1.1\r\n\r\n");
assertTest($written > 0, 'SecureSocket writes payload over connection');
$response = $socket->read();
assertTest(str_contains($response, '200 OK'), 'SecureSocket reads expected response');

$socket->setSimulatedPeerSvid(new X509Svid($leafPair->getCertificatePem()));
assertTest($socket->getPeerSpiffeId() !== null, 'SecureSocket extracts peer SPIFFE ID from negotiated session');
$socket->close();

// =============================================================================
// MODULE 69C: L7 Zero-Trust Traffic Policy & Service Authorization
// =============================================================================

section('69C-1: ServiceIdentity — Authenticated Principal');

$frontendId = ServiceIdentity::fromSpiffe('spiffe://cluster.local/ns/prod/sa/frontend', 'mtls');
assertTest($frontendId->getServiceName() === 'frontend', 'ServiceIdentity identifies service name');
assertTest($frontendId->getNamespace() === 'prod', 'ServiceIdentity identifies namespace');
assertTest($frontendId->getTrustDomain() === 'cluster.local', 'ServiceIdentity identifies trust domain');
assertTest($frontendId->getAuthMethod() === 'mtls', 'ServiceIdentity records authentication mechanism');

section('69C-2: MeshRule — Matchers & Wildcards');

$rule = MeshRule::allow('allow-frontend-orders')
    ->from('spiffe://cluster.local/ns/prod/sa/frontend')
    ->to('spiffe://cluster.local/ns/prod/sa/order-service')
    ->methods(['GET', 'POST'])
    ->paths(['/api/orders', '/api/orders/*'])
    ->headers(['x-tenant-id' => '*']);

$orderTarget = new SpiffeId('spiffe://cluster.local/ns/prod/sa/order-service');

assertTest($rule->matches($frontendId, $orderTarget, 'GET', '/api/orders', ['x-tenant-id' => 'acme']), 'MeshRule matches valid caller, target, method, path, and header');
assertTest($rule->matches($frontendId, $orderTarget, 'POST', '/api/orders/123', ['x-tenant-id' => 'globex']), 'MeshRule matches subpath wildcard');
assertTest(!$rule->matches($frontendId, $orderTarget, 'DELETE', '/api/orders/123', ['x-tenant-id' => 'globex']), 'MeshRule rejects unlisted HTTP method');
assertTest(!$rule->matches($frontendId, $orderTarget, 'GET', '/admin/settings', ['x-tenant-id' => 'globex']), 'MeshRule rejects unauthorized path');
assertTest(!$rule->matches($frontendId, $orderTarget, 'GET', '/api/orders', []), 'MeshRule rejects request missing required header');

section('69C-3: AccessDecision & MeshPolicy — Zero-Trust Default Deny');

$policy = MeshPolicy::create('order-service');
$policy->addRule($rule);

$allowDecision = $policy->evaluate($frontendId, $orderTarget, 'GET', '/api/orders', ['x-tenant-id' => 't1']);
assertTest($allowDecision->isAllowed(), 'MeshPolicy allows request matching rule');
assertTest($allowDecision->getMatchedRule() === 'allow-frontend-orders', 'AccessDecision records matched rule name');

// Unauthorized service
$rogueId = ServiceIdentity::fromSpiffe('spiffe://cluster.local/ns/dev/sa/test-runner');
$denyDecision = $policy->evaluate($rogueId, $orderTarget, 'GET', '/api/orders', ['x-tenant-id' => 't1']);
assertTest($denyDecision->isDenied(), 'MeshPolicy enforces Zero-Trust Default Deny on untrusted caller');
assertTest(str_contains($denyDecision->getReason(), 'Default Deny'), 'AccessDecision records Default Deny reason');

section('69C-4: PolicyEngine — Multi-Service Policies & Audit Log');

$engine = new PolicyEngine();
$engine->registerPolicy($policy);

assertTest($engine->getPolicyCount() === 1, 'PolicyEngine registers and counts policies');

$dec1 = $engine->authorize($frontendId, $orderTarget, 'POST', '/api/orders/99', ['x-tenant-id' => 'bd']);
assertTest($dec1->isAllowed(), 'PolicyEngine delegates authorization to matching service policy');

$dec2 = $engine->authorize($rogueId, $orderTarget, 'GET', '/secret');
assertTest($dec2->isDenied(), 'PolicyEngine rejects unauthorized request');

$audit = $engine->getAuditLog();
assertTest(count($audit) === 2, 'PolicyEngine records audit trail entries for access decisions');
assertTest($audit[0]['allowed'] === true && $audit[1]['allowed'] === false, 'PolicyEngine audit log tracks both allow and deny outcomes');

// =============================================================================
// MODULE 69D: Service Mesh Topology & Sidecar Proxy Orchestrator
// =============================================================================

section('69D-1: MeshNode — Service Representation & Liveness');

$node = MeshNode::create('payment-svc', 'spiffe://cluster.local/ns/prod/sa/payment-svc', '10.0.0.5', 8080, 8443);
assertTest($node->getServiceName() === 'payment-svc', 'MeshNode stores service name');
assertTest($node->getHost() === '10.0.0.5' && $node->getMtlsPort() === 8443, 'MeshNode tracks endpoint and mTLS port');
assertTest($node->isAlive(), 'MeshNode reports alive on creation');

$node->setStatus('DEGRADED');
assertTest($node->getStatus() === 'DEGRADED', 'MeshNode status mutates correctly');

section('69D-2: MeshTopology — Graph Dependencies & Graphviz DOT');

$topo = new MeshTopology();
$topo->addNode($node);
$topo->addNode(MeshNode::create('order-svc', 'spiffe://cluster.local/ns/prod/sa/order-svc'));
$topo->addNode(MeshNode::create('inventory-svc', 'spiffe://cluster.local/ns/prod/sa/inventory-svc'));

$topo->recordTraffic('order-svc', 'payment-svc');
$topo->recordTraffic('order-svc', 'payment-svc');
$topo->recordTraffic('order-svc', 'inventory-svc');

assertTest(in_array('payment-svc', $topo->getDependencies('order-svc'), true), 'MeshTopology maps outgoing service dependencies');
assertTest(in_array('order-svc', $topo->getConsumers('payment-svc'), true), 'MeshTopology maps incoming service consumers');
assertTest($topo->getTrafficMatrix()['order-svc']['payment-svc'] === 2, 'MeshTopology records request call volume between services');

$dot = $topo->toDot();
assertTest(str_contains($dot, 'digraph ServiceMesh'), 'MeshTopology generates Graphviz digraph definition');
assertTest(str_contains($dot, '"order-svc" -> "payment-svc"'), 'MeshTopology DOT export includes directed traffic edges');

section('69D-3: SidecarProxy — Inbound Interception & Outbound Forwarding');

$sidecar = new SidecarProxy(
    serviceName: 'order-service',
    localSpiffeId: $orderTarget,
    certificatePair: $leafPair,
    trustedCaPem: $meshCa->getCaCertificatePem(),
    policyEngine: $engine,
    topology: $topo,
    simulate: true
);

// Inbound authorized
$inboundDecision = $sidecar->handleInbound($frontendId, 'GET', '/api/orders', ['x-tenant-id' => '100']);
assertTest($inboundDecision->isAllowed(), 'SidecarProxy enforces inbound policy check allowing authorized caller');

// Inbound denied
$deniedInbound = $sidecar->handleInbound($rogueId, 'GET', '/api/orders');
assertTest($deniedInbound->isDenied(), 'SidecarProxy blocks unauthorized caller at the sidecar boundary');

// Outbound forwarding
$outboundResp = $sidecar->forwardOutbound('payment-service', new SpiffeId('spiffe://cluster.local/ns/prod/sa/payment-svc'), 'POST', '/charge');
assertTest($outboundResp['status_code'] === 200, 'SidecarProxy forwards outbound mTLS call to target workload');

section('69D-4: ServiceMeshManager — Central Facade & Global Helpers');

ServiceMeshManager::resetInstance();
$mgr = ServiceMeshManager::create('cluster.local');

$certOrder = $mgr->registerService('order-service', 'prod');
assertTest($certOrder instanceof CertificatePair, 'ServiceMeshManager registers service and returns CertificatePair');
assertTest($mgr->getTopology()->getNode('order-service') !== null, 'ServiceMeshManager populates service node in topology');

$policy = $mgr->definePolicy('order-service');
assertTest($policy instanceof MeshPolicy, 'ServiceMeshManager defines policy fluently');

$sidecarCreated = $mgr->createSidecar('order-service');
assertTest($sidecarCreated instanceof SidecarProxy, 'ServiceMeshManager creates pre-configured SidecarProxy instance');

$stats = $mgr->getStats();
assertTest($stats['trust_domain'] === 'cluster.local', 'ServiceMeshManager stats report trust domain');
assertTest($stats['active_nodes'] >= 1, 'ServiceMeshManager stats report active node count');

// Global Helpers
assertTest(service_mesh() instanceof ServiceMeshManager, 'Global service_mesh() resolves singleton');
assertTest((string) mesh_spiffe_id('cluster.local', 'auth') === 'spiffe://cluster.local/auth', 'Global mesh_spiffe_id() constructs SpiffeId');
assertTest((string) spiffe_id('auth', 'prod', 'cluster.local') === 'spiffe://cluster.local/ns/prod/sa/auth', 'Global spiffe_id() constructs SpiffeId');
assertTest(mesh_policy('auth-service') instanceof MeshPolicy, 'Global mesh_policy() constructs MeshPolicy');
assertTest(is_resource(mtls_context($certOrder, $mgr->getCa()->getCaCertificatePem())), 'Global mtls_context() constructs stream SSL resource');

// =============================================================================
// SUMMARY
// =============================================================================

$total = $passed + $failed;
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    PHASE 69 TEST SUMMARY                    ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
printf("║  Passed: %-3d   Failed: %-3d   Total: %-3d                   ║\n", $passed, $failed, $total);
echo "╚══════════════════════════════════════════════════════════════╝\n";

if ($failed > 0) {
    echo "\nFailed assertions:\n";
    foreach ($errors as $err) {
        echo "  ✗ {$err}\n";
    }
    exit(1);
}
echo "\n✅ All Phase 69 tests passed!\n\n";
exit(0);
