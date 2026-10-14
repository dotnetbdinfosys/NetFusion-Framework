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

namespace NetFusion\Core\ServiceMesh\Proxy;

use NetFusion\Core\ServiceMesh\Contracts\CircuitBreakerInterface;
use NetFusion\Core\ServiceMesh\Contracts\LoadBalancerInterface;
use NetFusion\Core\ServiceMesh\Contracts\SidecarProxyInterface;
use NetFusion\Core\ServiceMesh\Exceptions\AccessDeniedMeshException;
use NetFusion\Core\ServiceMesh\Exceptions\CircuitOpenException;
use NetFusion\Core\ServiceMesh\Exceptions\FaultInjectedException;
use NetFusion\Core\ServiceMesh\Exceptions\ServiceNotFoundException;
use NetFusion\Core\ServiceMesh\Health\ServiceInstance;
use NetFusion\Core\ServiceMesh\LoadBalancer\RoundRobinLoadBalancer;
use NetFusion\Core\ServiceMesh\Resilience\MeshCircuitBreaker;
use NetFusion\Core\ServiceMesh\Resilience\MeshRateLimiter;
use NetFusion\Core\ServiceMesh\Resilience\OutlierDetector;
use NetFusion\Core\ServiceMesh\Routing\RetryPolicy;
use NetFusion\Core\ServiceMesh\Routing\TrafficRoute;
use NetFusion\Core\ServiceMesh\Security\MeshAuthorizationPolicy;
use NetFusion\Core\ServiceMesh\Security\SecurityContext;
use NetFusion\Core\ServiceMesh\Security\SpiffeId;
use NetFusion\Core\ServiceMesh\ServiceMeshManager;

class SidecarProxy implements SidecarProxyInterface
{
    protected string $serviceName;
    protected ServiceMeshManager $meshManager;
    protected SpiffeId $localSpiffeId;

    /** @var callable|null Local inbound request handler */
    protected $inboundHandler = null;
    /** @var callable|null Mock/Real outbound transport handler */
    protected $transportHandler = null;

    protected LoadBalancerInterface $loadBalancer;
    protected OutlierDetector $outlierDetector;
    protected ?MeshRateLimiter $rateLimiter = null;
    /** @var array<string, MeshCircuitBreaker> */
    protected array $circuitBreakers = [];

    public function __construct(
        string $serviceName,
        ServiceMeshManager $meshManager,
        ?SpiffeId $spiffeId = null
    ) {
        $this->serviceName = $serviceName;
        $this->meshManager = $meshManager;
        $this->localSpiffeId = $spiffeId ?? SpiffeId::of($serviceName);
        $this->loadBalancer = new RoundRobinLoadBalancer();
        $this->outlierDetector = new OutlierDetector();
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getLocalSpiffeId(): SpiffeId
    {
        return $this->localSpiffeId;
    }

    public function setInboundHandler(callable $handler): self
    {
        $this->inboundHandler = $handler;
        return $this;
    }

    public function setTransportHandler(callable $handler): self
    {
        $this->transportHandler = $handler;
        return $this;
    }

    public function setLoadBalancer(LoadBalancerInterface $loadBalancer): self
    {
        $this->loadBalancer = $loadBalancer;
        return $this;
    }

    public function setRateLimiter(MeshRateLimiter $rateLimiter): self
    {
        $this->rateLimiter = $rateLimiter;
        return $this;
    }

    public function getCircuitBreaker(string $targetService): MeshCircuitBreaker
    {
        if (!isset($this->circuitBreakers[$targetService])) {
            $this->circuitBreakers[$targetService] = new MeshCircuitBreaker($targetService);
        }
        return $this->circuitBreakers[$targetService];
    }

    public function getOutlierDetector(): OutlierDetector
    {
        return $this->outlierDetector;
    }

    /**
     * Intercept incoming service call, verify mTLS, AuthZ and RateLimiter.
     */
    public function handleInbound(MeshRequestContext $context): MeshResponse
    {
        $start = microtime(true);

        // 1. Resolve & Authenticate Security Context
        $secCtx = $context->getSecurityContext();
        if ($secCtx === null) {
            $clientCert = $context->getHeader('X-Forwarded-Client-Cert');
            $spiffeHeader = $context->getHeader('X-Mesh-Spiffe-Id');

            if ($clientCert !== null) {
                $verified = $this->meshManager->getCertificateManager()->verifyCertificate($clientCert);
                if ($verified['valid'] && $verified['spiffe_id'] !== null) {
                    $secCtx = SecurityContext::authenticated(
                        SpiffeId::fromString($verified['spiffe_id']),
                        $context->getHeader('X-Forwarded-For')
                    );
                }
            } elseif ($spiffeHeader !== null) {
                $secCtx = SecurityContext::authenticated(
                    SpiffeId::fromString($spiffeHeader),
                    $context->getHeader('X-Forwarded-For')
                );
            } else {
                $secCtx = SecurityContext::anonymous($context->getHeader('X-Forwarded-For'));
            }
            $context = $context->withSecurityContext($secCtx);
        }

        // 2. Evaluate Zero-Trust Authorization Policies
        $policies = $this->meshManager->getPolicies();
        if (!empty($policies)) {
            $allowed = false;
            $hasMatchingDeny = false;

            foreach ($policies as $policy) {
                if ($policy->matches($secCtx, $this->serviceName, $context->getMethod(), $context->getPath(), $context->getHeaders())) {
                    if ($policy->getAction() === MeshAuthorizationPolicy::ACTION_DENY) {
                        $hasMatchingDeny = true;
                        break;
                    }
                    if ($policy->getAction() === MeshAuthorizationPolicy::ACTION_ALLOW) {
                        $allowed = true;
                    }
                }
            }

            if ($hasMatchingDeny || !$allowed) {
                return MeshResponse::error(403, "Access Denied: Service mesh authorization policy rejected request from " . ($secCtx->getPeerSpiffeId()?->toString() ?? 'anonymous'));
            }
        }

        // 3. Evaluate Rate Limiting
        if ($this->rateLimiter !== null) {
            $rateKey = $secCtx->getPeerSpiffeId()?->toString() ?? $context->getHeader('X-Forwarded-For', 'default');
            if (!$this->rateLimiter->consume($rateKey)) {
                return MeshResponse::error(429, "Too Many Requests: Mesh rate limit exceeded");
            }
        }

        // 4. Dispatch to local service handler
        if ($this->inboundHandler !== null) {
            try {
                $res = call_user_func($this->inboundHandler, $context);
                $latency = (microtime(true) - $start) * 1000.0;

                if ($res instanceof MeshResponse) {
                    return $res;
                }
                return new MeshResponse(200, [], $res, null, null, $latency);
            } catch (\Throwable $e) {
                return MeshResponse::error(500, $e->getMessage());
            }
        }

        return MeshResponse::ok(['service' => $this->serviceName, 'status' => 'handled']);
    }

    /**
     * Intercept outbound service call, route, balance, inject faults, retry, and execute.
     */
    public function handleOutbound(MeshRequestContext $context): MeshResponse
    {
        $start = microtime(true);
        $targetService = $context->getTargetService();

        // 1. Inject caller identity headers
        $context = $context->withSource($this->serviceName, $this->localSpiffeId)
            ->withHeader('X-Mesh-Spiffe-Id', $this->localSpiffeId->toString())
            ->withHeader('X-Request-Id', $context->getRequestId());

        // 2. Find matching TrafficRoute
        $route = $this->meshManager->matchRoute($context);
        $targetSubset = 'v1';

        if ($route !== null) {
            // Apply TrafficSplitter
            if ($route->getSplitter() !== null) {
                $targetSubset = $route->getSplitter()->resolveSubset($context);
            }

            // Apply Fault Injection (Chaos testing)
            if ($route->getFaultInjector() !== null) {
                try {
                    $route->getFaultInjector()->execute();
                } catch (FaultInjectedException $fe) {
                    return MeshResponse::error($fe->getHttpStatusCode(), $fe->getMessage());
                }
            }
        }

        // 3. Resolve Candidate Service Instances
        $allInstances = $this->meshManager->getInstances($targetService);
        if (empty($allInstances)) {
            return MeshResponse::error(503, "Service Unavailable: No healthy instances found for [{$targetService}] in mesh registry");
        }

        // Filter by subset if applicable
        $subsetInstances = array_values(array_filter(
            $allInstances,
            fn(ServiceInstance $i) => $i->getSubset() === $targetSubset && $i->isAvailable()
        ));

        $candidateInstances = !empty($subsetInstances) ? $subsetInstances : $allInstances;

        // 4. Select instance via Load Balancer
        $selectedInstance = $this->loadBalancer->choose($candidateInstances, $context);
        if ($selectedInstance === null) {
            return MeshResponse::error(503, "Service Unavailable: All instances for [{$targetService}] are currently unhealthy or quarantined");
        }

        // 5. Check Circuit Breaker
        $cb = $this->getCircuitBreaker($targetService);
        if ($route === null || $route->isCircuitBreakerEnabled()) {
            if (!$cb->allowRequest()) {
                return MeshResponse::error(503, "Circuit Breaker OPEN: Target service [{$targetService}] is failing");
            }
        }

        // 6. Execute Request with Retry Policy
        $retryPolicy = $route?->getRetryPolicy() ?? RetryPolicy::of(1, 10, [500, 502, 503, 504]);
        $attempt = 0;
        $lastResponse = null;

        $selectedInstance->incrementActiveConnections();

        do {
            $attemptStart = microtime(true);
            try {
                $response = $this->executeTransport($selectedInstance, $context);
                $latency = (microtime(true) - $attemptStart) * 1000.0;

                // Record in Outlier Detector & Circuit Breaker
                $this->outlierDetector->recordResult($selectedInstance, $response->getStatusCode(), $latency);

                if ($response->isSuccess()) {
                    $cb->recordSuccess();
                } else {
                    $cb->recordFailure();
                }

                $lastResponse = new MeshResponse(
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $response->getBody(),
                    $selectedInstance->getId(),
                    $selectedInstance->getSubset(),
                    (microtime(true) - $start) * 1000.0,
                    false,
                    $response->getError()
                );

                if (!$retryPolicy->shouldRetry($attempt, $response->getStatusCode())) {
                    break;
                }
            } catch (\Throwable $e) {
                $cb->recordFailure();
                $this->outlierDetector->recordResult($selectedInstance, 500, (microtime(true) - $attemptStart) * 1000.0);
                $lastResponse = MeshResponse::error(500, $e->getMessage());

                if (!$retryPolicy->shouldRetry($attempt, 500)) {
                    break;
                }
            }

            $attempt++;
            if ($attempt < $retryPolicy->getMaxRetries()) {
                $backoffMs = $retryPolicy->getBackoffMs($attempt);
                usleep($backoffMs * 1000);
            }
        } while ($attempt < $retryPolicy->getMaxRetries());

        $selectedInstance->decrementActiveConnections();

        // 7. Handle Shadow Traffic Asynchronously
        if ($route?->getSplitter()?->getShadowSubset() !== null) {
            $shadowSubset = $route->getSplitter()->getShadowSubset();
            $shadowCandidates = array_values(array_filter(
                $allInstances,
                fn(ServiceInstance $i) => $i->getSubset() === $shadowSubset && $i->isAvailable()
            ));
            if (!empty($shadowCandidates)) {
                $shadowInst = $shadowCandidates[0];
                try {
                    $this->executeTransport($shadowInst, $context);
                } catch (\Throwable) {
                    // Shadow errors do not affect caller
                }
            }
        }

        return $lastResponse ?? MeshResponse::error(500, "Unknown mesh proxy error");
    }

    /**
     * Dispatch HTTP/transport call to remote service instance.
     */
    protected function executeTransport(ServiceInstance $instance, MeshRequestContext $context): MeshResponse
    {
        if ($this->transportHandler !== null) {
            $res = call_user_func($this->transportHandler, $instance, $context);
            if ($res instanceof MeshResponse) {
                return $res;
            }
            if (is_array($res)) {
                return new MeshResponse(200, [], $res);
            }
            return MeshResponse::ok($res);
        }

        // Default transport invocation for microservices
        return MeshResponse::ok([
            'service' => $instance->getServiceName(),
            'instance_id' => $instance->getId(),
            'subset' => $instance->getSubset(),
            'path' => $context->getPath(),
            'method' => $context->getMethod(),
            'caller' => $context->getSourceSpiffeId()?->toString(),
        ]);
    }
}
