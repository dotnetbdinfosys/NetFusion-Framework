<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/RpcManager.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC
| Purpose   : Central RPC Coordinator & Service Mesh Gateway.
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

namespace NetFusion\Core\RPC;

use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
use NetFusion\Core\RPC\CircuitBreaker\CircuitBreaker;
use NetFusion\Core\RPC\Client\RpcClient;
use NetFusion\Core\RPC\Client\RpcProxy;
use NetFusion\Core\RPC\Contracts\CircuitBreakerInterface;
use NetFusion\Core\RPC\Contracts\LoadBalancerInterface;
use NetFusion\Core\RPC\Contracts\ServiceRegistryInterface;
use NetFusion\Core\RPC\LoadBalancer\LeastConnectionsLoadBalancer;
use NetFusion\Core\RPC\LoadBalancer\RandomLoadBalancer;
use NetFusion\Core\RPC\LoadBalancer\RoundRobinLoadBalancer;
use NetFusion\Core\RPC\Registry\Drivers\ArrayRegistryDriver;
use NetFusion\Core\RPC\Registry\Drivers\DatabaseRegistryDriver;
use NetFusion\Core\RPC\Registry\Drivers\FileRegistryDriver;
use NetFusion\Core\RPC\Registry\ServiceRegistry;
use NetFusion\Core\RPC\Server\RpcServer;

class RpcManager
{
    protected ?RpcServer $server = null;
    protected ?RpcClient $client = null;
    protected ?ServiceRegistry $registry = null;
    protected ?LoadBalancerInterface $loadBalancer = null;
    /** @var array<string, CircuitBreakerInterface> */
    protected array $circuitBreakers = [];

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get or instantiate the ServiceRegistry.
     */
    public function registry(): ServiceRegistry
    {
        if ($this->registry === null) {
            $config = $this->app->make(Config::class);
            $driverName = (string) $config->get('rpc.registry.driver', 'array');

            $this->registry = new ServiceRegistry();

            // Register default drivers
            $this->registry->addDriver('array', new ArrayRegistryDriver());
            $this->registry->addDriver('file', new FileRegistryDriver($this->app->storagePath('framework/rpc_registry.json')));

            if ($this->app->has(\NetFusion\Core\Database\DatabaseManager::class)) {
                $db = $this->app->make(\NetFusion\Core\Database\DatabaseManager::class);
                $this->registry->addDriver('database', new DatabaseRegistryDriver($db));
            }

            $this->registry->setDefaultDriver($driverName);
        }

        return $this->registry;
    }

    /**
     * Get or instantiate the Load Balancer.
     */
    public function loadBalancer(): LoadBalancerInterface
    {
        if ($this->loadBalancer === null) {
            $config = $this->app->make(Config::class);
            $strategy = (string) $config->get('rpc.load_balancer', 'round_robin');

            $this->loadBalancer = match ($strategy) {
                'random'            => new RandomLoadBalancer(),
                'least_connections' => new LeastConnectionsLoadBalancer(),
                default             => new RoundRobinLoadBalancer(),
            };
        }

        return $this->loadBalancer;
    }

    /**
     * Get or instantiate the RPC Server daemon instance.
     */
    public function server(): RpcServer
    {
        if ($this->server === null) {
            $this->server = new RpcServer($this->app, $this->registry());
        }

        return $this->server;
    }

    /**
     * Register a service handler on the RPC Server.
     */
    public function register(string $serviceName, object|string $handler): self
    {
        $this->server()->register($serviceName, $handler);
        return $this;
    }

    /**
     * Get or instantiate the RPC Client.
     */
    public function client(): RpcClient
    {
        if ($this->client === null) {
            $config = $this->app->make(Config::class);
            $timeout = (float) $config->get('rpc.timeout', 5.0);
            $retries = (int) $config->get('rpc.retries', 0);

            $this->client = new RpcClient(
                registry: $this->registry(),
                loadBalancer: $this->loadBalancer(),
                defaultTimeout: $timeout,
                defaultRetries: $retries
            );

            // Connect local server for fast zero-latency in-process dispatch
            $this->client->setLocalServer($this->server());
        }

        return $this->client;
    }

    /**
     * Get a fluent proxy for invoking a remote service.
     */
    public function service(string $serviceName, array $options = []): RpcProxy
    {
        return $this->client()->service($serviceName, $options);
    }

    /**
     * Direct invocation helper.
     */
    public function call(string $service, string $method, array $params = [], array $options = []): mixed
    {
        return $this->client()->call($service, $method, $params, $options);
    }

    /**
     * Get or configure Circuit Breaker for a service name.
     */
    public function circuitBreaker(
        string $serviceName,
        int $failureThreshold = 5,
        float $resetTimeoutSeconds = 30.0,
        int $halfOpenSuccessThreshold = 2
    ): CircuitBreaker {
        if (!isset($this->circuitBreakers[$serviceName])) {
            $this->circuitBreakers[$serviceName] = new CircuitBreaker(
                serviceName: $serviceName,
                failureThreshold: $failureThreshold,
                resetTimeoutSeconds: $resetTimeoutSeconds,
                halfOpenSuccessThreshold: $halfOpenSuccessThreshold
            );
        }

        return $this->circuitBreakers[$serviceName];
    }
}
