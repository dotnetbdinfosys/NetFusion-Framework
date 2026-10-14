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

namespace NetFusion\Core\Gateway;

use NetFusion\Core\Gateway\Contracts\GatewayStoreInterface;
use NetFusion\Core\Gateway\Contracts\LoadBalancerInterface;
use NetFusion\Core\Gateway\Contracts\RateLimiterInterface;
use NetFusion\Core\Gateway\Exceptions\GatewayException;
use NetFusion\Core\Gateway\Exceptions\RateLimitExceededException;
use NetFusion\Core\Gateway\Exceptions\UpstreamUnavailableException;
use NetFusion\Core\Gateway\Health\CircuitBreaker;
use NetFusion\Core\Gateway\Health\HealthChecker;
use NetFusion\Core\Gateway\LoadBalancer\RoundRobinBalancer;
use NetFusion\Core\Gateway\Models\GatewayRequest;
use NetFusion\Core\Gateway\Models\GatewayResponse;
use NetFusion\Core\Gateway\Models\Route;
use NetFusion\Core\Gateway\Models\Upstream;
use NetFusion\Core\Gateway\Models\UpstreamPool;
use NetFusion\Core\Gateway\Proxy\ReverseProxy;
use NetFusion\Core\Gateway\Router\GatewayRouter;
use NetFusion\Core\Gateway\Storage\DatabaseGatewayStore;
use NetFusion\Core\Gateway\Transform\RequestTransformer;

/**
 * Central Gateway Manager — orchestrates routing, load balancing,
 * health checking, rate limiting, transformation, and proxying.
 *
 * Usage:
 *   $gw = GatewayManager::create();
 *   $gw->route('api', '/api', $pool)->methods('GET', 'POST');
 *   $response = $gw->handle($request);
 */
class GatewayManager
{
    private GatewayRouter       $router;
    private LoadBalancerInterface $loadBalancer;
    private ReverseProxy        $proxy;
    private RequestTransformer  $transformer;
    private CircuitBreaker      $circuitBreaker;
    private ?RateLimiterInterface $rateLimiter = null;
    private ?HealthChecker      $healthChecker = null;

    /** @var array<string, mixed> Gateway configuration */
    private array $config;

    public function __construct(
        private readonly GatewayStoreInterface $store,
        array $config = []
    ) {
        $this->config         = array_merge($this->defaultConfig(), $config);
        $this->router         = new GatewayRouter();
        $this->loadBalancer   = new RoundRobinBalancer();
        $this->proxy          = new ReverseProxy(
            connectTimeoutSeconds: $this->config['connect_timeout'],
            readTimeoutSeconds:    $this->config['read_timeout'],
            simulate:              $this->config['simulate'] ?? false,
        );
        $this->transformer    = new RequestTransformer();
        $this->circuitBreaker = new CircuitBreaker(
            failureThreshold: $this->config['circuit_breaker']['failure_threshold'],
            cooldownSeconds:  $this->config['circuit_breaker']['cooldown_seconds'],
        );
    }

    /**
     * Creates a GatewayManager with in-memory storage (for testing / quick start).
     */
    public static function create(array $config = []): self
    {
        return new self(new DatabaseGatewayStore(), $config);
    }

    // -------------------------------------------------------------------------
    // Fluent configuration
    // -------------------------------------------------------------------------

    /**
     * Registers a route from a path prefix + upstream pool.
     */
    public function route(string $id, string $pathPrefix, UpstreamPool $pool, string $hostname = '*'): Route
    {
        $route = new Route($id, $pathPrefix, $pool, $hostname);
        $this->router->addRoute($route);
        return $route;
    }

    /**
     * Adds a pre-built Route to the gateway.
     */
    public function addRoute(Route $route): self
    {
        $this->router->addRoute($route);
        return $this;
    }

    public function useLoadBalancer(LoadBalancerInterface $lb): self
    {
        $this->loadBalancer = $lb;
        return $this;
    }

    public function useRateLimiter(RateLimiterInterface $limiter): self
    {
        $this->rateLimiter = $limiter;
        return $this;
    }

    public function useHealthChecker(HealthChecker $checker): self
    {
        $this->healthChecker = $checker;
        return $this;
    }

    public function useProxy(ReverseProxy $proxy): self
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function useTransformer(RequestTransformer $transformer): self
    {
        $this->transformer = $transformer;
        return $this;
    }

    public function getTransformer(): RequestTransformer
    {
        return $this->transformer;
    }

    public function getRouter(): GatewayRouter
    {
        return $this->router;
    }

    public function getCircuitBreaker(): CircuitBreaker
    {
        return $this->circuitBreaker;
    }

    // -------------------------------------------------------------------------
    // Core request handling
    // -------------------------------------------------------------------------

    /**
     * Handles an incoming GatewayRequest and returns a GatewayResponse.
     * This is the main entry point for all proxied traffic.
     *
     * @throws GatewayException
     */
    public function handle(GatewayRequest $request): GatewayResponse
    {
        // 1. Rate limiting check
        if ($this->rateLimiter !== null) {
            if (!$this->rateLimiter->consume($request)) {
                $key = $request->getRateLimitKey();
                throw new RateLimitExceededException(
                    message: 'Rate limit exceeded',
                    key: $key,
                    resetAt: $this->rateLimiter->getResetTime($key)
                );
            }
        }

        // 2. Route matching
        $route = $this->router->match($request);
        if ($route === null) {
            return GatewayResponse::error(404, 'No matching route', "Path: {$request->getPath()}");
        }

        // 3. Load balancing — select upstream
        $pool     = $route->getUpstreamPool();
        $upstream = $this->loadBalancer->select($pool, $request);
        if ($upstream === null) {
            throw new UpstreamUnavailableException('No healthy upstream', $route->getId());
        }

        // 4. Circuit breaker check
        if (!$this->circuitBreaker->isAllowed($upstream)) {
            throw new UpstreamUnavailableException('Circuit breaker open for upstream ' . $upstream->getId(), $route->getId());
        }

        // 5. Request transformation
        $transformedRequest = $this->transformer->transformRequest($request, $route);

        // 6. Proxy (forward) to upstream
        /** @var \NetFusion\Core\Gateway\Models\Upstream $upstream */
        if ($upstream instanceof Upstream) {
            $upstream->incrementConnections();
        }

        $upstreamResponse = $this->proxy->forward($transformedRequest, $upstream);

        if ($upstream instanceof Upstream) {
            $upstream->decrementConnections();
            $upstream->recordLatency($upstreamResponse->getLatencyMs());
        }

        // 7. Circuit breaker feedback
        if ($upstreamResponse->isServerError()) {
            $this->circuitBreaker->recordFailure($upstream);
        } else {
            $this->circuitBreaker->recordSuccess($upstream);
        }

        // 8. Response transformation
        $finalResponse = $this->transformer->transformResponse($upstreamResponse, $request);

        // 9. Log request
        $this->store->logRequest([
            'route_id'    => $route->getId(),
            'upstream_id' => $upstream->getId(),
            'method'      => $request->getMethod(),
            'path'        => $request->getPath(),
            'status_code' => $finalResponse->getStatusCode(),
            'latency_ms'  => $finalResponse->getLatencyMs(),
            'client_ip'   => $request->getClientIp(),
        ]);

        return $finalResponse;
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Runs health checks on all upstreams for all routes.
     *
     * @return array<string, bool>
     */
    public function checkHealth(): array
    {
        $results = [];
        $checker = $this->healthChecker ?? new HealthChecker(simulate: true);

        foreach ($this->router->getRoutes() as $route) {
            foreach ($route->getUpstreamPool()->all() as $upstream) {
                $healthy = $checker->checkUpstream($upstream);
                $results[$upstream->getId()] = $healthy;

                // Persist health state
                $this->store->saveUpstreamHealth(
                    $upstream->getId(),
                    $healthy,
                    $this->circuitBreaker->getFailures($upstream)
                );
            }
        }
        return $results;
    }

    /**
     * Returns a summary of all configured routes.
     *
     * @return array[]
     */
    public function getRoutesSummary(): array
    {
        return array_map(fn(Route $r) => $r->toArray(), $this->router->getRoutes());
    }

    private function defaultConfig(): array
    {
        return [
            'connect_timeout' => 5,
            'read_timeout'    => 30,
            'circuit_breaker' => [
                'failure_threshold' => 5,
                'cooldown_seconds'  => 30,
            ],
            'simulate'        => false,
        ];
    }
}
