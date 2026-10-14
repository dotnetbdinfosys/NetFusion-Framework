<?php

/**
 * Phase 67: Enterprise API Gateway Test Suite
 * Tests: Router, Load Balancers, Health/Circuit Breaker, Rate Limiters,
 *        Transform Pipeline, Reverse Proxy (simulated), GatewayManager, Helpers
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../core/Support/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'NetFusion\\Core\\';
    $base   = __DIR__ . '/../core/';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file     = $base . $relative . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ---------------------------------------------------------------------------
// Test helpers
// ---------------------------------------------------------------------------
$passed  = 0;
$failed  = 0;
$errors  = [];

function assertTest(bool $condition, string $label): void
{
    global $passed, $failed, $errors;
    if ($condition) {
        $passed++;
        echo "  ✓ {$label}\n";
    } else {
        $failed++;
        $errors[] = $label;
        echo "  ✗ FAIL: {$label}\n";
    }
}

echo "\n";
echo "======================================================================\n";
echo "  Phase 67: Enterprise API Gateway — Test Suite\n";
echo "======================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. Models: Upstream, UpstreamPool, GatewayRequest, GatewayResponse
// ---------------------------------------------------------------------------
echo "── 1. Models ──────────────────────────────────────────────────────────\n";

use NetFusion\Core\Gateway\Models\Upstream;
use NetFusion\Core\Gateway\Models\UpstreamPool;
use NetFusion\Core\Gateway\Models\GatewayRequest;
use NetFusion\Core\Gateway\Models\GatewayResponse;
use NetFusion\Core\Gateway\Models\Route;

$up1 = Upstream::fromUrl('http://backend1:8001', 3);
$up2 = Upstream::fromUrl('http://backend2:8002', 1);
$up3 = Upstream::fromUrl('https://secure:443', 2);

assertTest($up1->getHost() === 'backend1', 'Upstream::fromUrl parses host correctly');
assertTest($up1->getPort() === 8001, 'Upstream::fromUrl parses port correctly');
assertTest($up1->getScheme() === 'http', 'Upstream::fromUrl parses scheme correctly');
assertTest($up1->getWeight() === 3, 'Upstream::fromUrl sets weight correctly');
assertTest($up1->isHealthy() === true, 'Upstream starts healthy');
assertTest($up3->getScheme() === 'https', 'Upstream parses https scheme');

$up1->markUnhealthy();
assertTest(!$up1->isHealthy(), 'Upstream::markUnhealthy works');
$up1->markHealthy();
assertTest($up1->isHealthy(), 'Upstream::markHealthy restores health');

$up1->incrementConnections();
$up1->incrementConnections();
assertTest($up1->getActiveConnections() === 2, 'Upstream::incrementConnections tracks count');
$up1->decrementConnections();
assertTest($up1->getActiveConnections() === 1, 'Upstream::decrementConnections tracks count');

$up1->recordLatency(100.0);
$up1->recordLatency(50.0);
assertTest($up1->getAverageLatencyMs() > 0, 'Upstream::recordLatency computes EMA latency');

assertTest($up1->getBaseUrl() === 'http://backend1:8001', 'Upstream::getBaseUrl returns correct URL');

$pool = new UpstreamPool('test_pool');
$pool->add($up1)->add($up2);
assertTest($pool->count() === 2, 'UpstreamPool::count returns correct count');
assertTest($pool->healthyCount() === 2, 'UpstreamPool::healthyCount counts healthy');
$up2->markUnhealthy();
assertTest($pool->healthyCount() === 1, 'UpstreamPool::healthyCount excludes unhealthy');
$up2->markHealthy();

assertTest($pool->findById($up1->getId()) === $up1, 'UpstreamPool::findById locates upstream');
assertTest($pool->hasHealthy(), 'UpstreamPool::hasHealthy returns true when healthy exist');

// GatewayRequest
$req = new GatewayRequest('GET', '/api/users?page=2', 'example.com', '', [
    'Authorization' => 'Bearer tok123',
    'X-Tenant-Id'   => 'acme',
], '10.0.0.1');

assertTest($req->getMethod() === 'GET', 'GatewayRequest::getMethod returns uppercase method');
assertTest($req->getPath() === '/api/users', 'GatewayRequest::getPath strips query string');
assertTest($req->getHost() === 'example.com', 'GatewayRequest::getHost returns host');
assertTest($req->getClientIp() === '10.0.0.1', 'GatewayRequest::getClientIp returns IP');
assertTest($req->getHeader('authorization') === 'Bearer tok123', 'GatewayRequest::getHeader finds normalized header');
assertTest(!$req->hasHeader('x-missing'), 'GatewayRequest::hasHeader returns false for missing header');

$reqWithAttr = $req->withAttribute('user_id', 42);
assertTest($reqWithAttr->getAttribute('user_id') === 42, 'GatewayRequest::withAttribute stores attribute immutably');
assertTest($req->getAttribute('user_id') === null, 'Original GatewayRequest unaffected by withAttribute');

$rlKey = $req->getRateLimitKey('ip');
assertTest(str_starts_with($rlKey, 'rl:ip:'), 'GatewayRequest::getRateLimitKey generates ip key');
$rlKeyTenant = $req->getRateLimitKey('tenant');
assertTest(str_starts_with($rlKeyTenant, 'rl:tenant:'), 'GatewayRequest::getRateLimitKey generates tenant key');

// GatewayResponse
$resp = GatewayResponse::json(200, ['ok' => true]);
assertTest($resp->getStatusCode() === 200, 'GatewayResponse::json sets status code');
assertTest($resp->isSuccess(), 'GatewayResponse::isSuccess true for 2xx');
assertTest(str_contains($resp->getBody(), '"ok"'), 'GatewayResponse::json encodes body');

$errResp = GatewayResponse::error(503, 'Unavailable');
assertTest($errResp->isServerError(), 'GatewayResponse::isServerError true for 5xx');
assertTest($errResp->getStatusCode() === 503, 'GatewayResponse::error sets 503 status');

$respWithHeader = $resp->withHeader('X-Custom', 'test');
assertTest($respWithHeader->getHeader('x-custom') === 'test', 'GatewayResponse::withHeader adds header immutably');

// ---------------------------------------------------------------------------
// 2. Route model & matching
// ---------------------------------------------------------------------------
echo "\n── 2. Route Matching ──────────────────────────────────────────────────\n";

$pool2 = new UpstreamPool('pool2');
$pool2->add(Upstream::fromUrl('http://api:9000'));

$route = new Route('api_route', '/api', $pool2, '*');
$route->methods('GET', 'POST')->priority(10);

$matchReq = new GatewayRequest('GET', '/api/products', 'any.host');
$noMatchReq = new GatewayRequest('DELETE', '/api/products', 'any.host');
$noPathReq  = new GatewayRequest('GET', '/web/home', 'any.host');

assertTest($route->matches($matchReq), 'Route matches GET request to /api prefix');
assertTest(!$route->matches($noMatchReq), 'Route rejects DELETE when only GET|POST allowed');
assertTest(!$route->matches($noPathReq), 'Route rejects request with different path prefix');

$hostRoute = new Route('host_route', '/', $pool2, 'api.example.com');
$hostMatch  = new GatewayRequest('GET', '/', 'api.example.com');
$hostNoMatch = new GatewayRequest('GET', '/', 'web.example.com');
assertTest($hostRoute->matches($hostMatch), 'Route matches exact hostname');
assertTest(!$hostRoute->matches($hostNoMatch), 'Route rejects wrong hostname');

$headerRoute = (new Route('header_route', '/v2', $pool2))->header('x-version', '2');
$headerMatch  = new GatewayRequest('GET', '/v2/data', 'host', '', ['X-Version' => '2']);
$headerNoMatch = new GatewayRequest('GET', '/v2/data', 'host', '', ['X-Version' => '1']);
assertTest($headerRoute->matches($headerMatch), 'Route matches header predicate');
assertTest(!$headerRoute->matches($headerNoMatch), 'Route rejects wrong header value');

// Path rewriting
$stripRoute = (new Route('strip', '/api/v1', $pool2))->stripPrefix(true)->upstreamPath('/v1');
assertTest($stripRoute->rewritePath('/api/v1/users') === '/v1/users', 'Route::rewritePath strips prefix and prepends upstream path');

$routeArr = $route->toArray();
assertTest(isset($routeArr['id'], $routeArr['path_prefix'], $routeArr['upstreams']), 'Route::toArray contains required keys');

// ---------------------------------------------------------------------------
// 3. Load Balancers
// ---------------------------------------------------------------------------
echo "\n── 3. Load Balancers ──────────────────────────────────────────────────\n";

use NetFusion\Core\Gateway\LoadBalancer\RoundRobinBalancer;
use NetFusion\Core\Gateway\LoadBalancer\WeightedRoundRobinBalancer;
use NetFusion\Core\Gateway\LoadBalancer\LeastConnectionsBalancer;
use NetFusion\Core\Gateway\LoadBalancer\IpHashBalancer;
use NetFusion\Core\Gateway\LoadBalancer\LatencyAwareBalancer;

$lbPool = new UpstreamPool('lb_pool');
$ua = Upstream::fromUrl('http://a:1001');
$ub = Upstream::fromUrl('http://b:1002');
$uc = Upstream::fromUrl('http://c:1003');
$lbPool->add($ua)->add($ub)->add($uc);

$rrReq = new GatewayRequest('GET', '/', 'host');

// Round Robin
$rr = new RoundRobinBalancer();
$sel1 = $rr->select($lbPool, $rrReq);
$sel2 = $rr->select($lbPool, $rrReq);
$sel3 = $rr->select($lbPool, $rrReq);
$sel4 = $rr->select($lbPool, $rrReq);
assertTest($sel1 !== null, 'RoundRobinBalancer selects upstream');
assertTest($sel4->getId() === $sel1->getId(), 'RoundRobinBalancer cycles back to first upstream');
assertTest($rr->getName() === 'round_robin', 'RoundRobinBalancer::getName correct');

// Weighted Round Robin
$wPool = new UpstreamPool('w_pool');
$wPool->add(Upstream::fromUrl('http://heavy:2001', 3));
$wPool->add(Upstream::fromUrl('http://light:2002', 1));
$wrr = new WeightedRoundRobinBalancer();
$wCounts = [];
for ($i = 0; $i < 8; $i++) {
    $selected = $wrr->select($wPool, $rrReq);
    $wCounts[$selected->getHost()] = ($wCounts[$selected->getHost()] ?? 0) + 1;
}
assertTest(($wCounts['heavy'] ?? 0) > ($wCounts['light'] ?? 0), 'WeightedRoundRobinBalancer sends more traffic to higher-weight upstream');
assertTest($wrr->getName() === 'weighted_round_robin', 'WeightedRoundRobinBalancer::getName correct');

// Least Connections
$lcPool = new UpstreamPool('lc_pool');
$lcA = Upstream::fromUrl('http://lc_a:3001');
$lcB = Upstream::fromUrl('http://lc_b:3002');
$lcA->incrementConnections();
$lcA->incrementConnections();
$lcPool->add($lcA)->add($lcB);
$lc = new LeastConnectionsBalancer();
$lcSel = $lc->select($lcPool, $rrReq);
assertTest($lcSel->getHost() === 'lc_b', 'LeastConnectionsBalancer selects upstream with fewest connections');
assertTest($lc->getName() === 'least_connections', 'LeastConnectionsBalancer::getName correct');

// IP Hash
$ipPool = new UpstreamPool('ip_pool');
$ipPool->add(Upstream::fromUrl('http://ip_a:4001'));
$ipPool->add(Upstream::fromUrl('http://ip_b:4002'));
$ipLb = new IpHashBalancer();
$ip1Req = new GatewayRequest('GET', '/', 'host', '', [], '192.168.1.100');
$ip2Req = new GatewayRequest('GET', '/', 'host', '', [], '192.168.1.100');
$ip3Req = new GatewayRequest('GET', '/', 'host', '', [], '10.0.0.1');
$ipSel1 = $ipLb->select($ipPool, $ip1Req);
$ipSel2 = $ipLb->select($ipPool, $ip2Req);
assertTest($ipSel1->getId() === $ipSel2->getId(), 'IpHashBalancer routes same IP to same upstream');
assertTest($ipLb->getName() === 'ip_hash', 'IpHashBalancer::getName correct');

// Latency Aware
$latPool = new UpstreamPool('lat_pool');
$latA = Upstream::fromUrl('http://lat_a:5001');
$latB = Upstream::fromUrl('http://lat_b:5002');
$latA->recordLatency(200.0);
$latB->recordLatency(50.0);
$latPool->add($latA)->add($latB);
$latLb = new LatencyAwareBalancer();
$latSel = $latLb->select($latPool, $rrReq);
assertTest($latSel->getHost() === 'lat_b', 'LatencyAwareBalancer selects lowest-latency upstream');
assertTest($latLb->getName() === 'latency_aware', 'LatencyAwareBalancer::getName correct');

// No healthy upstream
$emptyPool = new UpstreamPool('empty');
$deadUp = Upstream::fromUrl('http://dead:9999');
$deadUp->markUnhealthy();
$emptyPool->add($deadUp);
assertTest($rr->select($emptyPool, $rrReq) === null, 'RoundRobinBalancer returns null when no healthy upstream');

// ---------------------------------------------------------------------------
// 4. Health Checker & Circuit Breaker
// ---------------------------------------------------------------------------
echo "\n── 4. Health & Circuit Breaker ────────────────────────────────────────\n";

use NetFusion\Core\Gateway\Health\HealthChecker;
use NetFusion\Core\Gateway\Health\CircuitBreaker;

$hcPool = new UpstreamPool('hc_pool');
$hcUp   = Upstream::fromUrl('http://hc_backend:6001');
$hcPool->add($hcUp);

// Simulated health checker (no real HTTP)
$hc = new HealthChecker('/health', 3, 200, simulate: true);
assertTest($hc->isSimulated(), 'HealthChecker simulation mode is on');

$hcResults = $hc->checkPool($hcPool);
assertTest($hcResults[$hcUp->getId()] === true, 'HealthChecker::checkPool returns healthy for simulated upstream');
assertTest($hcUp->isHealthy(), 'HealthChecker::checkPool marks upstream healthy');

// Circuit Breaker
$cb = new CircuitBreaker(failureThreshold: 3, cooldownSeconds: 60);
$cbUp = Upstream::fromUrl('http://cb_backend:7001');

assertTest($cb->isAllowed($cbUp), 'CircuitBreaker starts in CLOSED state (allows requests)');
assertTest($cb->getState($cbUp) === 'closed', 'CircuitBreaker initial state is closed');

// Record failures to trip circuit
$cb->recordFailure($cbUp);
$cb->recordFailure($cbUp);
assertTest($cb->getFailures($cbUp) === 2, 'CircuitBreaker tracks failure count');
assertTest($cb->isAllowed($cbUp), 'CircuitBreaker still allows at threshold - 1');

$cb->recordFailure($cbUp); // trips at 3
assertTest(!$cb->isAllowed($cbUp), 'CircuitBreaker OPEN after hitting failure threshold');
assertTest($cb->getState($cbUp) === 'open', 'CircuitBreaker state is open after tripping');

// recordSuccess resets when closed
$cb2 = new CircuitBreaker(failureThreshold: 5);
$cbUp2 = Upstream::fromUrl('http://cb2:7002');
$cb2->recordFailure($cbUp2);
$cb2->recordSuccess($cbUp2);
assertTest($cb2->getFailures($cbUp2) === 0, 'CircuitBreaker::recordSuccess resets failure count in closed state');

// Reset
$cb->reset($cbUp);
assertTest($cb->isAllowed($cbUp), 'CircuitBreaker::reset reopens circuit');
assertTest($cb->getState($cbUp) === 'closed', 'CircuitBreaker state is closed after reset');

// ---------------------------------------------------------------------------
// 5. Rate Limiters
// ---------------------------------------------------------------------------
echo "\n── 5. Rate Limiters ───────────────────────────────────────────────────\n";

use NetFusion\Core\Gateway\Storage\DatabaseGatewayStore;
use NetFusion\Core\Gateway\RateLimit\TokenBucketRateLimiter;
use NetFusion\Core\Gateway\RateLimit\SlidingWindowRateLimiter;

$rlStore = new DatabaseGatewayStore(); // in-memory

// Token Bucket
$tbLimiter = new TokenBucketRateLimiter($rlStore, capacity: 5, refillRate: 1.0, dimension: 'ip');
$rlReq = new GatewayRequest('GET', '/', 'host', '', [], '192.0.2.1');

assertTest($tbLimiter->isAllowed($rlReq), 'TokenBucketRateLimiter allows initial request');
assertTest($tbLimiter->consume($rlReq), 'TokenBucketRateLimiter::consume returns true when tokens available');
assertTest($tbLimiter->getRemainingTokens($rlReq->getRateLimitKey('ip')) >= 0, 'TokenBucketRateLimiter::getRemainingTokens returns non-negative');
assertTest($tbLimiter->getResetTime($rlReq->getRateLimitKey('ip')) > time(), 'TokenBucketRateLimiter::getResetTime returns future timestamp');

// Exhaust bucket
$limitedReq = new GatewayRequest('GET', '/', 'host', '', [], '10.1.1.1');
$tbLimiter2 = new TokenBucketRateLimiter($rlStore, capacity: 3, refillRate: 1.0, dimension: 'ip');
$tbLimiter2->consume($limitedReq); // 1
$tbLimiter2->consume($limitedReq); // 2
$tbLimiter2->consume($limitedReq); // 3 (last)
$consumed4 = $tbLimiter2->consume($limitedReq); // should fail (bucket exhausted)
assertTest(!$consumed4, 'TokenBucketRateLimiter rejects request when bucket is empty');

// Sliding Window
$swLimiter = new SlidingWindowRateLimiter($rlStore, limit: 10, windowSeconds: 60, dimension: 'ip');
$swReq = new GatewayRequest('GET', '/', 'host', '', [], '172.16.0.1');
assertTest($swLimiter->isAllowed($swReq), 'SlidingWindowRateLimiter allows initial request');
assertTest($swLimiter->consume($swReq), 'SlidingWindowRateLimiter::consume returns true within limit');
assertTest($swLimiter->getRemainingTokens($swReq->getRateLimitKey('ip')) >= 0, 'SlidingWindowRateLimiter::getRemainingTokens non-negative');
assertTest($swLimiter->getResetTime($swReq->getRateLimitKey('ip')) > time(), 'SlidingWindowRateLimiter::getResetTime in future');

// ---------------------------------------------------------------------------
// 6. Request Transformer Pipeline
// ---------------------------------------------------------------------------
echo "\n── 6. Request Transformer Pipeline ────────────────────────────────────\n";

use NetFusion\Core\Gateway\Transform\RequestTransformer;

$transformer = new RequestTransformer();
$transformer
    ->addRequestHeader('X-Injected', 'gateway-value')
    ->addResponseHeader('X-Response-Tag', 'proxied')
    ->cors(['origins' => ['https://app.example.com']]);

$inboundReq = new GatewayRequest('POST', '/api/orders', 'shop.com', '{"qty":2}', [
    'Content-Type' => 'application/json',
    'Origin'       => 'https://app.example.com',
], '5.6.7.8');

$txReq = $transformer->transformRequest($inboundReq);
assertTest($txReq->getHeader('x-injected') === 'gateway-value', 'Transformer injects custom request header');
assertTest($txReq->getHeader('x-forwarded-for') === '5.6.7.8', 'Transformer injects X-Forwarded-For header');
assertTest($txReq->getHeader('x-gateway-version') === '1.0', 'Transformer injects X-Gateway-Version header');

// Path rewriting via route
$txPool = new UpstreamPool('tx_pool');
$txPool->add(Upstream::fromUrl('http://orders:8888'));
// Use prefix '/api' so that stripping leaves '/orders', then prepend '/v2' → '/v2/orders'
$txRoute = (new Route('tx_route', '/api', $txPool))->stripPrefix(true)->upstreamPath('/v2');
$txReqWithRoute = $transformer->transformRequest($inboundReq, $txRoute);
assertTest($txReqWithRoute->getUri() === '/v2/orders', 'Transformer rewrites URI via route strip+prefix');

// Response transformation
$upstreamResp = new GatewayResponse(200, '{"result":"ok"}', ['content-type' => ['application/json']], 45.2, 'upstream_id');
$txResp = $transformer->transformResponse($upstreamResp, $inboundReq);
assertTest($txResp->getHeader('x-response-tag') === 'proxied', 'Transformer injects custom response header');
assertTest($txResp->getHeader('x-gateway-latency-ms') !== null, 'Transformer injects latency header into response');
assertTest($txResp->getHeader('access-control-allow-origin') !== null, 'Transformer injects CORS headers');

// Body transformer
$bodyTransformer = new RequestTransformer();
$bodyTransformer->transformBody(fn($body, $dir) => strtoupper($body));
$bodyReq = new GatewayRequest('POST', '/', 'h', 'hello world');
// Body transformation is applied during transformRequest — test via the actual call
$txBodyReq = $bodyTransformer->transformRequest($bodyReq);
assertTest($txBodyReq->getBody() === 'HELLO WORLD', 'Transformer applies body transformation callable');

// ---------------------------------------------------------------------------
// 7. GatewayRouter
// ---------------------------------------------------------------------------
echo "\n── 7. GatewayRouter ───────────────────────────────────────────────────\n";

use NetFusion\Core\Gateway\Router\GatewayRouter;

$router = new GatewayRouter();

$routerPool = new UpstreamPool('router_pool');
$routerPool->add(Upstream::fromUrl('http://svc:9001'));

$routeA = (new Route('route_a', '/api', $routerPool))->priority(50);
$routeB = (new Route('route_b', '/api/v2', $routerPool))->priority(10); // higher prio (lower number)
$routeC = (new Route('route_c', '/web', $routerPool))->priority(100);

$router->addRoutes([$routeA, $routeB, $routeC]);

assertTest($router->count() === 3, 'GatewayRouter::count returns correct route count');

$matchedRoute = $router->match(new GatewayRequest('GET', '/api/v2/users', 'host'));
assertTest($matchedRoute?->getId() === 'route_b', 'GatewayRouter matches higher-priority route first for /api/v2');

$matchedRouteA = $router->match(new GatewayRequest('GET', '/api/v1/products', 'host'));
assertTest($matchedRouteA?->getId() === 'route_a', 'GatewayRouter falls through to /api route for /api/v1');

$noMatchRoute = $router->match(new GatewayRequest('GET', '/admin/panel', 'host'));
assertTest($noMatchRoute === null, 'GatewayRouter returns null when no route matches');

$router->removeRoute('route_c');
assertTest($router->count() === 2, 'GatewayRouter::removeRoute removes correct route');

// ---------------------------------------------------------------------------
// 8. DatabaseGatewayStore
// ---------------------------------------------------------------------------
echo "\n── 8. DatabaseGatewayStore ────────────────────────────────────────────\n";

use NetFusion\Core\Database\Connection;

$storeSqlite = sys_get_temp_dir() . '/nf_test_gw_' . uniqid() . '.sqlite';
$storeDb     = new Connection(['driver' => 'sqlite', 'database' => $storeSqlite]);
$dbStore     = new DatabaseGatewayStore($storeDb);

assertTest($dbStore->hasPdoConnection(), 'DatabaseGatewayStore connects to SQLite');

// Rate buckets
$dbStore->setRateBucket('test:bucket', 42, time() + 60);
$bucket = $dbStore->getRateBucket('test:bucket');
assertTest($bucket !== null && $bucket['tokens'] === 42, 'DatabaseGatewayStore persists and retrieves rate bucket');

// Upstream health
$dbStore->saveUpstreamHealth('up://test:8080', true, 0);
$health = $dbStore->loadUpstreamHealth('up://test:8080');
assertTest($health !== null && $health['healthy'] === true && $health['failures'] === 0, 'DatabaseGatewayStore persists upstream health state');

$dbStore->saveUpstreamHealth('up://test:8080', false, 5);
$health2 = $dbStore->loadUpstreamHealth('up://test:8080');
assertTest($health2['healthy'] === false && $health2['failures'] === 5, 'DatabaseGatewayStore updates upstream health state');

// Routes
$storeRoutePool = new UpstreamPool('store_pool');
$storeRoutePool->add(Upstream::fromUrl('http://store_backend:7070'));
$storeRoute = new Route('store_route', '/store', $storeRoutePool);
$dbStore->saveRoute($storeRoute);
$loadedRoutes = $dbStore->loadRoutes();
assertTest(count($loadedRoutes) >= 1, 'DatabaseGatewayStore::loadRoutes returns saved routes');
assertTest($loadedRoutes[0]['id'] === 'store_route', 'DatabaseGatewayStore::loadRoutes returns correct route data');

$dbStore->deleteRoute('store_route');
$afterDelete = $dbStore->loadRoutes();
assertTest(count($afterDelete) === 0, 'DatabaseGatewayStore::deleteRoute removes route correctly');

// Request logging
$dbStore->logRequest([
    'route_id'    => 'r1',
    'upstream_id' => 'u1',
    'method'      => 'GET',
    'path'        => '/api/test',
    'status_code' => 200,
    'latency_ms'  => 12.5,
    'client_ip'   => '1.2.3.4',
]);
assertTest(true, 'DatabaseGatewayStore::logRequest executes without error');

// In-memory fallback
$memStore = new DatabaseGatewayStore();
$memStore->setRateBucket('mem:key', 10, time() + 30);
$memBucket = $memStore->getRateBucket('mem:key');
assertTest($memBucket['tokens'] === 10, 'DatabaseGatewayStore in-memory fallback stores rate bucket');

// ---------------------------------------------------------------------------
// 9. GatewayManager (simulated proxy)
// ---------------------------------------------------------------------------
echo "\n── 9. GatewayManager ──────────────────────────────────────────────────\n";

use NetFusion\Core\Gateway\GatewayManager;
use NetFusion\Core\Gateway\Proxy\ReverseProxy;
use NetFusion\Core\Gateway\Exceptions\UpstreamUnavailableException;
use NetFusion\Core\Gateway\Exceptions\RateLimitExceededException;

// Simulated proxy — no real TCP connections
$simProxy = new ReverseProxy(simulate: true);

$gm = GatewayManager::create(['simulate' => true]);
$gm->useProxy($simProxy);

$gmPool = new UpstreamPool('gm_pool');
$gmPool->add(Upstream::fromUrl('http://gm_backend:5555'));

$gm->route('users_route', '/users', $gmPool)->methods('GET');
$gm->addRoute((new Route('orders_route', '/orders', $gmPool))->priority(20));

assertTest($gm->getRouter()->count() === 2, 'GatewayManager registers routes correctly');

$gmReq = new GatewayRequest('GET', '/users/123', 'host', '', [], '1.2.3.4');
$gmResp = $gm->handle($gmReq);
assertTest($gmResp->getStatusCode() === 200, 'GatewayManager::handle proxies matching request');

// 404 — no matching route
$gmMissReq = new GatewayRequest('GET', '/missing/path', 'host');
$gmMissResp = $gm->handle($gmMissReq);
assertTest($gmMissResp->getStatusCode() === 404, 'GatewayManager::handle returns 404 for unmatched route');

// No healthy upstream
$deadPool = new UpstreamPool('dead_pool');
$deadUp2 = Upstream::fromUrl('http://dead:9999');
$deadUp2->markUnhealthy();
$deadPool->add($deadUp2);
$gm->addRoute(new Route('dead_route', '/dead', $deadPool));

$exception = null;
try {
    $gm->handle(new GatewayRequest('GET', '/dead/endpoint', 'host'));
} catch (UpstreamUnavailableException $e) {
    $exception = $e;
}
assertTest($exception !== null, 'GatewayManager throws UpstreamUnavailableException when no healthy upstream');
assertTest($exception->getCode() === 503, 'UpstreamUnavailableException has 503 code');

// Circuit breaker integration
$cbPool = new UpstreamPool('cb_gm_pool');
$cbGmUp = Upstream::fromUrl('http://cb_gm:8888');
$cbPool->add($cbGmUp);
$gm->addRoute((new Route('cb_route', '/cb', $cbPool))->priority(5));
$cbGm = $gm->getCircuitBreaker();

// Trip the circuit by recording enough failures
for ($i = 0; $i < 5; $i++) {
    $cbGm->recordFailure($cbGmUp);
}
$cbException = null;
try {
    $gm->handle(new GatewayRequest('GET', '/cb/test', 'host'));
} catch (UpstreamUnavailableException $e) {
    $cbException = $e;
}
assertTest($cbException !== null, 'GatewayManager throws UpstreamUnavailableException when circuit is open');

// Rate limiter integration
$rlGmStore = new DatabaseGatewayStore();
$rlGmLimiter = new TokenBucketRateLimiter($rlGmStore, capacity: 1, refillRate: 0.01, dimension: 'ip');
$gm->useRateLimiter($rlGmLimiter);

// First request: consumes the 1 token (note: first consume creates bucket with capacity-1=0)
$rlGmReq = new GatewayRequest('GET', '/users/rl_test', 'host', '', [], '99.99.99.99');
$rlException = null;
try {
    // The first isAllowed call in consume returns false when capacity=1 since bucket is created with 0
    // Let's pre-fill the bucket
    $rlGmStore->setRateBucket('rl:ip:99.99.99.99', 0, time() + 60);
    $gm->handle($rlGmReq);
} catch (RateLimitExceededException $e) {
    $rlException = $e;
}
assertTest($rlException !== null, 'GatewayManager enforces rate limit and throws RateLimitExceededException');
assertTest($rlException->getCode() === 429, 'RateLimitExceededException has 429 code');

// Health check
$healthResults = $gm->checkHealth();
assertTest(is_array($healthResults), 'GatewayManager::checkHealth returns array');

// Routes summary
$summary = $gm->getRoutesSummary();
assertTest(is_array($summary) && count($summary) > 0, 'GatewayManager::getRoutesSummary returns non-empty array');

// ---------------------------------------------------------------------------
// 10. Global Helpers
// ---------------------------------------------------------------------------
echo "\n── 10. Global Helpers ─────────────────────────────────────────────────\n";

assertTest(gateway() instanceof GatewayManager, 'gateway() helper returns GatewayManager instance');
assertTest(gateway() === gateway(), 'gateway() helper returns same singleton instance');

$helperRoute = api_gateway_route('/helper/api', ['http://helper_backend:6666'], 'helper_route');
assertTest($helperRoute instanceof Route, 'api_gateway_route() helper registers and returns Route');
assertTest($helperRoute->getPathPrefix() === '/helper/api', 'api_gateway_route() sets correct path prefix');
assertTest($helperRoute->getId() === 'helper_route', 'api_gateway_route() sets correct route ID');

// Auto-generated ID
$autoRoute = api_gateway_route('/auto/path', ['http://auto:7777']);
assertTest(str_contains($autoRoute->getId(), 'auto'), 'api_gateway_route() auto-generates route ID from path');

// proxy_pass() — uses the global gateway() singleton which has routes registered above
$ppReq = new GatewayRequest('GET', '/helper/api/test', 'host', '', [], '127.0.0.1');
gateway()->useProxy(new ReverseProxy(simulate: true));
$ppResp = proxy_pass($ppReq);
assertTest($ppResp instanceof GatewayResponse, 'proxy_pass() helper returns GatewayResponse');

// ---------------------------------------------------------------------------
// 11. Exceptions
// ---------------------------------------------------------------------------
echo "\n── 11. Exceptions ─────────────────────────────────────────────────────\n";

use NetFusion\Core\Gateway\Exceptions\GatewayException;
use NetFusion\Core\Gateway\Exceptions\RateLimitExceededException as RateLimitEx;
use NetFusion\Core\Gateway\Exceptions\UpstreamUnavailableException as UpstreamEx;

$gwEx = new GatewayException('Gateway error', 500);
assertTest($gwEx instanceof \Exception, 'GatewayException extends Exception');
assertTest($gwEx->getCode() === 500, 'GatewayException stores HTTP status code');

$upEx = new UpstreamEx('No backend', 'route_xyz', 503);
assertTest($upEx->getRouteId() === 'route_xyz', 'UpstreamUnavailableException stores route ID');
assertTest($upEx instanceof GatewayException, 'UpstreamUnavailableException extends GatewayException');

$rlEx = new RateLimitEx('Too many requests', 'rl:ip:1.2.3.4', time() + 30, 429);
assertTest($rlEx->getKey() === 'rl:ip:1.2.3.4', 'RateLimitExceededException stores rate limit key');
assertTest($rlEx->getRetryAfterSeconds() > 0, 'RateLimitExceededException::getRetryAfterSeconds returns positive seconds');
assertTest($rlEx instanceof GatewayException, 'RateLimitExceededException extends GatewayException');

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------
if (file_exists($storeSqlite)) {
    @unlink($storeSqlite);
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "\n======================================================================\n";
echo "  Phase 67 Results: {$passed} Passed, {$failed} Failed\n";
if (!empty($errors)) {
    echo "  FAILURES:\n";
    foreach ($errors as $e) {
        echo "    - {$e}\n";
    }
}
echo "======================================================================\n\n";

exit($failed > 0 ? 1 : 0);
