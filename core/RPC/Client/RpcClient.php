<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Client/RpcClient.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Client
| Purpose   : Resilient RPC Client with Service Mesh Discovery, Load Balancing & Circuit Breakers.
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

namespace NetFusion\Core\RPC\Client;

use NetFusion\Core\RPC\CircuitBreaker\CircuitBreaker;
use NetFusion\Core\RPC\Contracts\CircuitBreakerInterface;
use NetFusion\Core\RPC\Contracts\LoadBalancerInterface;
use NetFusion\Core\RPC\Contracts\RpcClientInterface;
use NetFusion\Core\RPC\Contracts\RpcServerInterface;
use NetFusion\Core\RPC\Contracts\ServiceRegistryInterface;
use NetFusion\Core\RPC\LoadBalancer\RoundRobinLoadBalancer;
use NetFusion\Core\RPC\Registry\ServiceInstance;
use NetFusion\Core\RPC\RpcException;
use NetFusion\Core\RPC\RpcRequest;
use NetFusion\Core\RPC\RpcResponse;
use Throwable;

class RpcClient implements RpcClientInterface
{
    /**
     * @var array<string, CircuitBreakerInterface>
     */
    protected array $circuitBreakers = [];
    protected ?RpcServerInterface $localServer = null;

    public function __construct(
        protected ?ServiceRegistryInterface $registry = null,
        protected ?LoadBalancerInterface $loadBalancer = null,
        protected float $defaultTimeout = 5.0,
        protected int $defaultRetries = 0
    ) {
        $this->loadBalancer ??= new RoundRobinLoadBalancer();
    }

    public function setLocalServer(RpcServerInterface $server): self
    {
        $this->localServer = $server;
        return $this;
    }

    public function getCircuitBreaker(string $serviceName): CircuitBreakerInterface
    {
        if (!isset($this->circuitBreakers[$serviceName])) {
            $this->circuitBreakers[$serviceName] = new CircuitBreaker($serviceName);
        }
        return $this->circuitBreakers[$serviceName];
    }

    public function setCircuitBreaker(string $serviceName, CircuitBreakerInterface $cb): self
    {
        $this->circuitBreakers[$serviceName] = $cb;
        return $this;
    }

    public function service(string $service, array $options = []): RpcProxy
    {
        return new RpcProxy($this, $service, $options);
    }

    /**
     * Dispatch an RPC call with circuit breaker and load balancing.
     */
    public function call(string $service, string $method, array $params = [], array $options = []): mixed
    {
        $cb = $this->getCircuitBreaker($service);
        $fallback = $options['fallback'] ?? null;

        return $cb->execute(function () use ($service, $method, $params, $options) {
            return $this->executeCallWithRetry($service, $method, $params, $options);
        }, $fallback);
    }

    /**
     * Execute remote call with optional retries.
     */
    protected function executeCallWithRetry(string $service, string $method, array $params, array $options): mixed
    {
        $maxRetries = (int) ($options['retries'] ?? $this->defaultRetries);
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            try {
                return $this->dispatchCall($service, $method, $params, $options);
            } catch (Throwable $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt <= $maxRetries) {
                    $backoffUs = (int) (pow(2, $attempt - 1) * 50000); // 50ms, 100ms, 200ms
                    usleep($backoffUs);
                }
            }
        }

        throw $lastException ?? new RpcException("RPC call to [{$service}::{$method}] failed.");
    }

    /**
     * Dispatch single attempt.
     */
    protected function dispatchCall(string $service, string $method, array $params, array $options): mixed
    {
        $metadata = (array) ($options['metadata'] ?? []);

        // Propagate trace context if available
        if (function_exists('trace') && ($tracer = trace()) && method_exists($tracer, 'getCurrentSpan')) {
            $span = $tracer->getCurrentSpan();
            if ($span && method_exists($span, 'getContext')) {
                $metadata['traceparent'] = $span->getContext()->toTraceparent();
            }
        }

        $request = RpcRequest::create(
            service: $service,
            method: $method,
            params: $params,
            metadata: $metadata
        );

        // 1. In-process local server dispatch optimization
        if ($this->localServer !== null && method_exists($this->localServer, 'hasService') && $this->localServer->hasService($service)) {
            $rawResponse = $this->localServer->handle($request->toJson());
            $response = RpcResponse::fromJson($rawResponse);
            return $response->getResult();
        }

        // 2. Resolve target instance via direct option, or registry & load balancer
        $instance = $this->resolveTargetInstance($service, $options);
        if ($instance === null) {
            throw new RpcException(
                "No healthy instance found for service [{$service}].",
                RpcException::SERVICE_UNAVAILABLE
            );
        }

        // 3. Socket dispatch
        return $this->sendSocketRequest($instance, $request, (float) ($options['timeout'] ?? $this->defaultTimeout));
    }

    /**
     * Resolve target endpoint.
     */
    protected function resolveTargetInstance(string $service, array $options): ?ServiceInstance
    {
        if (isset($options['endpoint'])) {
            $parts = parse_url((string) $options['endpoint']);
            $host = $parts['host'] ?? '127.0.0.1';
            $port = (int) ($parts['port'] ?? 9500);
            return ServiceInstance::create($service, $host, $port);
        }

        if (isset($options['host']) && isset($options['port'])) {
            return ServiceInstance::create($service, (string) $options['host'], (int) $options['port']);
        }

        if ($this->registry !== null) {
            $instances = $this->registry->discover($service);
            if (!empty($instances)) {
                return $this->loadBalancer->select($instances, $service);
            }
        }

        return null;
    }

    /**
     * Send socket request over TCP and read response.
     */
    protected function sendSocketRequest(ServiceInstance $instance, RpcRequest $request, float $timeout): mixed
    {
        $remote = "tcp://{$instance->host}:{$instance->port}";
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout);

        if (!$socket) {
            throw new RpcException(
                "Failed to connect to RPC service [{$instance->serviceName}] at {$remote}: {$errstr} ({$errno})",
                RpcException::SERVICE_UNAVAILABLE
            );
        }

        stream_set_timeout($socket, (int) floor($timeout), (int) (($timeout - floor($timeout)) * 1000000));

        $payload = $request->toJson() . "\n";
        $written = @fwrite($socket, $payload);
        if ($written === false) {
            @fclose($socket);
            throw new RpcException("Failed to send RPC payload to {$remote}", RpcException::SERVICE_UNAVAILABLE);
        }

        $responseBuffer = '';
        while (!feof($socket)) {
            $chunk = @fgets($socket, 8192);
            if ($chunk === false) {
                break;
            }
            $responseBuffer .= $chunk;
            if (str_ends_with($chunk, "\n")) {
                break;
            }
        }

        @fclose($socket);

        if (trim($responseBuffer) === '') {
            throw new RpcException("Empty response received from RPC service [{$instance->serviceName}]", RpcException::TIMEOUT);
        }

        $response = RpcResponse::fromJson(trim($responseBuffer));
        return $response->getResult();
    }
}
