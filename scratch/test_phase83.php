<?php

declare(strict_types=1);

/**
 * Phase 83 Unit Test Suite: HashiCorp Consul Service Discovery, Health Probing, Dynamic DNS & SWIM Gossip Protocol
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\Consul\Catalog\HealthCheckEngine;
use NetFusion\Core\Consul\Catalog\ServiceCatalog;
use NetFusion\Core\Consul\ConsulManager;
use NetFusion\Core\Consul\Dns\DnsPacketParser;
use NetFusion\Core\Consul\Dns\DnsResolver;
use NetFusion\Core\Consul\Enums\CheckStatus;
use NetFusion\Core\Consul\Enums\CheckType;
use NetFusion\Core\Consul\Enums\DnsRecordType;
use NetFusion\Core\Consul\Enums\NodeStatus;
use NetFusion\Core\Consul\Exceptions\ServiceNotFoundException;
use NetFusion\Core\Consul\Gossip\GossipDisseminator;
use NetFusion\Core\Consul\Gossip\Messages\GossipMessage;
use NetFusion\Core\Consul\Gossip\SwimFailureDetector;
use NetFusion\Core\Consul\Models\DnsMessage;
use NetFusion\Core\Consul\Models\GossipNode;
use NetFusion\Core\Consul\Models\ServiceCheck;
use NetFusion\Core\Consul\Models\ServiceInstance;

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

echo "Starting Phase 83 Tests...\n";

// --- Test 1: Service Catalog Registration & Deduplication ---
$catalog = new ServiceCatalog();
$inst1 = new ServiceInstance('web-1', 'web', '10.0.0.1', 8080, ['v1', 'production'], ['env' => 'prod']);
$inst2 = new ServiceInstance('web-2', 'web', '10.0.0.2', 8080, ['v2', 'staging'], ['env' => 'stage']);
$catalog->registerService($inst1);
$catalog->registerService($inst2);

assertTest($catalog->hasService('web') && count($catalog->getServiceInstances('web')) === 2, "Test 1: Service Catalog registers instances properly");

// --- Test 2: Tag-Based Discovery & Filtering ---
$prodInstances = $catalog->filterByTags('web', ['production']);
$stageInstances = $catalog->filterByTags('web', ['staging']);
assertTest(
    count($prodInstances) === 1 && $prodInstances[0]->getId() === 'web-1' &&
    count($stageInstances) === 1 && $stageInstances[0]->getId() === 'web-2',
    "Test 2: Filter instances by tags correctly"
);

// --- Test 3: Load Balancing (Round-Robin & Weighted) ---
$pick1 = $catalog->loadBalance('web', 'round_robin');
$pick2 = $catalog->loadBalance('web', 'round_robin');
$pick3 = $catalog->loadBalance('web', 'round_robin');
assertTest(
    $pick1 !== null && $pick2 !== null && $pick1->getId() !== $pick2->getId() && $pick3->getId() === $pick1->getId(),
    "Test 3: Round-Robin load balancing distributes across instances"
);

// --- Test 4: Health Check Evaluation (TTL, Script) ---
$healthEngine = new HealthCheckEngine($catalog);
$ttlCheck = new ServiceCheck('web-1-ttl', 'TTL Check', CheckType::TTL, CheckStatus::PASSING, ['interval' => 5]);
$inst1->addCheck($ttlCheck);

$evalStatus = $healthEngine->evaluateCheck($ttlCheck);
assertTest($evalStatus === CheckStatus::PASSING && $inst1->isHealthy(), "Test 4: Healthy TTL check evaluates to PASSING");

// --- Test 5: Health Probing Status Propagation & Warning/Critical ---
$scriptCheck = new ServiceCheck('web-1-script', 'Script Check', CheckType::SCRIPT, CheckStatus::PASSING, [
    'scriptCallback' => function () {
        return CheckStatus::CRITICAL;
    }
]);
$inst1->addCheck($scriptCheck);
$healthEngine->evaluateCheck($scriptCheck);
$inst1->recomputeStatus();
assertTest($inst1->getStatus() === CheckStatus::CRITICAL && !$inst1->isHealthy(), "Test 5: Script check failure propagates critical status to instance");

// --- Test 6: Auto-Eviction of Dead Services ---
$fakeTime = microtime(true) + 100.0;
$result = $healthEngine->runAllChecks($fakeTime);
assertTest(in_array('web-1', $result['evicted']) && !$catalog->getInstance('web-1'), "Test 6: Critical service auto-evicted after threshold");

// --- Test 7: SWIM Protocol Direct Ping & Indirect Ping-Req Probing ---
$nodeA = new GossipNode('node-a', 'agent-a', '192.168.1.10', 8301);
$nodeB = new GossipNode('node-b', 'agent-b', '192.168.1.11', 8301);
$nodeC = new GossipNode('node-c', 'agent-c', '192.168.1.12', 8301);

$detector = new SwimFailureDetector($nodeA);
$detector->addMember($nodeB);
$detector->addMember($nodeC);

$pingResult = $detector->ping($nodeB);
assertTest($pingResult === true && $nodeB->getStatus() === NodeStatus::ALIVE, "Test 7: Direct SWIM ping marks node ALIVE");

// --- Test 8: SWIM Suspect Timeout to DEAD Transition ---
$nodeB->setStatus(NodeStatus::SUSPECT);
$now = microtime(true) + 10.0;
$deadList = $detector->checkSuspectTimeouts($now);
assertTest(count($deadList) === 1 && $deadList[0]->getId() === 'node-b' && $nodeB->getStatus() === NodeStatus::DEAD, "Test 8: Suspect node transitions to DEAD after timeout");

// --- Test 9: SWIM Gossip Infection & Lamport Incarnation Refutation ---
$disseminator = new GossipDisseminator($nodeA, $detector);
// Node A receives suspect message targeting itself with incarnation 0
$suspectMsg = new GossipMessage(GossipMessage::TYPE_SUSPECT, 'node-b', 'node-a', 1, 0, ['subjectId' => 'node-a']);
$refuted = $disseminator->handleIncomingMessage($suspectMsg);
assertTest($refuted === true && $nodeA->getIncarnation() === 1 && $nodeA->getStatus() === NodeStatus::ALIVE, "Test 9: Self-suspect refuted with higher incarnation number");

// --- Test 10: Dynamic DNS Resolver (<service>.service.consul -> A records) ---
$dnsCatalog = new ServiceCatalog();
$api1 = new ServiceInstance('api-1', 'api', '10.0.0.50', 9000);
$api2 = new ServiceInstance('api-2', 'api', '10.0.0.51', 9000);
$dnsCatalog->registerService($api1);
$dnsCatalog->registerService($api2);

$resolver = new DnsResolver($dnsCatalog, 'consul');
$aRecords = $resolver->resolveQuery('api.service.consul', DnsRecordType::A);
assertTest(
    count($aRecords) === 2 &&
    $aRecords[0]['data'] === '10.0.0.50' &&
    $aRecords[1]['data'] === '10.0.0.51',
    "Test 10: DNS Resolver returns A records for api.service.consul"
);

// --- Test 11: Dynamic DNS SRV Resolution (_<service>._tcp.service.consul) ---
$srvRecords = $resolver->resolveQuery('_api._tcp.service.consul', DnsRecordType::SRV);
assertTest(
    count($srvRecords) === 2 &&
    $srvRecords[0]['type'] === DnsRecordType::SRV &&
    $srvRecords[0]['data']['port'] === 9000,
    "Test 11: DNS Resolver returns SRV records with port and target"
);

// --- Test 12: RFC 1035 Binary DNS Wire-Format Parser & Serializer ---
$parser = new DnsPacketParser();
$dnsQuery = new DnsMessage(1234);
$dnsQuery->addQuestion('api.service.consul', DnsRecordType::A);
$rawPacket = $parser->serialize($dnsQuery);

$parsedMsg = $parser->parse($rawPacket);
$questions = $parsedMsg->getQuestions();
assertTest(
    $parsedMsg->getId() === 1234 &&
    count($questions) === 1 &&
    $questions[0]['name'] === 'api.service.consul' &&
    $questions[0]['type'] === DnsRecordType::A,
    "Test 12: Binary RFC 1035 packet serialized and parsed correctly"
);

// --- Test 13: End-to-End DNS Binary Query Handling ---
$responseMsg = $resolver->resolve($parsedMsg);
$responsePacket = $parser->serialize($responseMsg);
$parsedResponse = $parser->parse($responsePacket);
assertTest(
    $parsedResponse->isResponse() &&
    $parsedResponse->getResponseCode() === 0,
    "Test 13: End-to-End DNS Binary query resolved with NOERROR status"
);

// --- Test 14: Master ConsulManager Facade & Service Registration ---
$manager = new ConsulManager([
    'node_id' => 'lead-node-01',
    'node_name' => 'dc1-lead',
    'dns_domain' => 'consul',
]);
$service = $manager->registerService('auth', '127.0.0.1', 4000, 'auth-1', ['v1'], ['env' => 'test'], [
    'type' => 'ttl',
    'interval' => 15,
]);
$discovered = $manager->discover('auth');
assertTest(
    count($discovered) === 1 &&
    $discovered[0]->getId() === 'auth-1' &&
    $manager->passHealthCheck('auth-1', 'auth-1-check'),
    "Test 14: ConsulManager registers, discovers and passes health check"
);

// --- Test 15: Global Helpers & Dependency Injection Container Binding ---
$app = new Application(dirname(__DIR__));
$app->singleton(ConsulManager::class, function () {
    return new ConsulManager(['node_name' => 'container-node']);
});
$app->singleton('consul', fn () => $app->make(ConsulManager::class));

$helperInstance = consul_register('payment', '10.0.1.10', 8000, ['v1']);
$found = consul_discover('payment');
$dnsLookup = consul_dns('payment.service.consul', 'A');

assertTest(
    count($found) === 1 &&
    $found[0]->getName() === 'payment' &&
    count($dnsLookup) === 1 &&
    $dnsLookup[0]['data'] === '10.0.1.10',
    "Test 15: Global helpers consul(), consul_register(), consul_discover(), consul_dns() work seamlessly"
);

echo "\nPhase 83 Unit Tests Completed: {$passed} Passed, {$failed} Failed.\n";
exit($failed === 0 ? 0 : 1);
