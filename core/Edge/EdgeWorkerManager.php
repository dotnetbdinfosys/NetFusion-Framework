<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Edge;

use NetFusion\Core\Edge\Cache\EdgeCache;
use NetFusion\Core\Edge\Enums\WorkerType;
use NetFusion\Core\Edge\Exceptions\WorkerNotFoundException;
use NetFusion\Core\Edge\Http\EdgeRequest;
use NetFusion\Core\Edge\Http\EdgeResponse;
use NetFusion\Core\Edge\Isolate\ExecutionContext;
use NetFusion\Core\Edge\Isolate\IsolatePool;
use NetFusion\Core\Edge\Worker\EdgeRouter;
use NetFusion\Core\Edge\Worker\WorkerRuntime;
use NetFusion\Core\Edge\Worker\WorkerScript;

/**
 * Master Enterprise Edge WebAssembly (Wasm) & Serverless Worker Fabric Manager.
 * Unified gateway facade coordinating Isolate Pool, Worker Registry, Edge Cache, and Dynamic Routing.
 */
class EdgeWorkerManager
{
    /** @var array<string, WorkerScript> */
    protected array $workers = [];
    protected EdgeRouter $router;
    protected EdgeCache $cache;
    protected IsolatePool $pool;
    protected WorkerRuntime $runtime;
    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config = [],
        ?IsolatePool $pool = null,
        ?EdgeCache $cache = null,
        ?EdgeRouter $router = null,
        ?WorkerRuntime $runtime = null
    ) {
        $this->config = $config;
        $this->pool = $pool ?? new IsolatePool(
            minSpare: (int)($config['pool']['min_spare'] ?? 2),
            maxCapacity: (int)($config['pool']['max_capacity'] ?? 32),
            defaultMemoryMb: (int)($config['pool']['default_memory_mb'] ?? 128),
            defaultCpuTimeoutMs: (int)($config['pool']['default_cpu_timeout_ms'] ?? 50)
        );
        $this->cache = $cache ?? new EdgeCache();
        $this->router = $router ?? new EdgeRouter();
        $this->runtime = $runtime ?? new WorkerRuntime($this->pool);
    }

    /**
     * Register a serverless worker script.
     *
     * @param callable|string $handler PHP callable or compiled Wasm binary string
     * @param array<string, string> $env
     */
    public function register(
        string $name,
        $handler,
        WorkerType $type = WorkerType::PHP,
        array $env = [],
        int $memoryLimitMb = 128,
        int $cpuTimeoutMs = 50,
        int $version = 1
    ): WorkerScript {
        $worker = new WorkerScript($name, $handler, $type, $env, $memoryLimitMb, $cpuTimeoutMs, $version);
        $this->workers[$name] = $worker;
        return $worker;
    }

    public function getWorker(string $name): ?WorkerScript
    {
        return $this->workers[$name] ?? null;
    }

    public function hasWorker(string $name): bool
    {
        return isset($this->workers[$name]);
    }

    /**
     * @return array<string, WorkerScript>
     */
    public function getWorkers(): array
    {
        return $this->workers;
    }

    /**
     * Register an edge routing pattern.
     *
     * @param array<string> $methods
     */
    public function route(string $pattern, string $worker, array $methods = [], int $weight = 100, ?string $country = null): self
    {
        $this->router->addRoute($pattern, $worker, $methods, $weight, $country);
        return $this;
    }

    public function getRouter(): EdgeRouter
    {
        return $this->router;
    }

    public function getCache(): EdgeCache
    {
        return $this->cache;
    }

    public function getPool(): IsolatePool
    {
        return $this->pool;
    }

    public function getRuntime(): WorkerRuntime
    {
        return $this->runtime;
    }

    /**
     * Explicitly invoke a worker by name.
     *
     * @throws WorkerNotFoundException
     */
    public function invoke(
        string $workerName,
        EdgeRequest|string $request,
        array $envOverrides = [],
        ?ExecutionContext $context = null
    ): EdgeResponse {
        $worker = $this->getWorker($workerName);
        if ($worker === null) {
            throw new WorkerNotFoundException($workerName);
        }

        $edgeReq = is_string($request) ? new EdgeRequest('GET', $request) : $request;

        // Apply env overrides if provided
        if (!empty($envOverrides)) {
            foreach ($envOverrides as $k => $v) {
                $worker->setEnvVar($k, (string)$v);
            }
        }

        return $this->runtime->dispatch($worker, $edgeReq, $context);
    }

    /**
     * Handle incoming gateway request using route matching & edge cache.
     */
    public function handle(EdgeRequest $request): EdgeResponse
    {
        // 1. Check Edge Cache
        $cached = $this->cache->match($request);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Match Route
        $workerName = $this->router->match($request);
        if ($workerName === null) {
            return new EdgeResponse("No edge worker matched route: " . $request->getPath(), 404);
        }

        // 3. Dispatch through worker runtime
        $response = $this->invoke($workerName, $request);

        // 4. Cache if response indicates cacheability
        $cacheControl = $response->getHeader('cache-control');
        if ($cacheControl !== null && str_contains($cacheControl, 'max-age=')) {
            preg_match('/max-age=(\d+)/', $cacheControl, $matches);
            $ttl = isset($matches[1]) ? (int)$matches[1] : 300;
            $this->cache->put($request, $response, $ttl);
        }

        return $response;
    }

    /**
     * Overall gateway telemetry statistics.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [
            'workers_count' => count($this->workers),
            'routes_count' => count($this->router->getRoutes()),
            'cache' => $this->cache->stats(),
            'isolates' => $this->pool->stats(),
        ];
    }
}
