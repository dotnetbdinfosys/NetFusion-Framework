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

namespace NetFusion\Core\ServiceMesh\Routing;

use JsonSerializable;
use NetFusion\Core\ServiceMesh\Proxy\MeshRequestContext;

class TrafficRoute implements JsonSerializable
{
    protected string $name;
    protected string $serviceName;
    protected ?string $pathPrefix = null;
    protected ?string $exactPath = null;
    /** @var array<string> */
    protected array $methods = [];
    /** @var array<string, string> */
    protected array $headers = [];

    protected int $timeoutMs = 5000;
    protected ?TrafficSplitter $splitter = null;
    protected ?FaultInjector $faultInjector = null;
    protected ?RetryPolicy $retryPolicy = null;
    protected bool $circuitBreaker = true;

    public function __construct(string $name, string $serviceName)
    {
        $this->name = $name;
        $this->serviceName = $serviceName;
    }

    public static function to(string $serviceName, string $name = ''): self
    {
        return new self($name ?: 'route_' . $serviceName, $serviceName);
    }

    public function prefix(string $pathPrefix): self
    {
        $this->pathPrefix = $pathPrefix;
        return $this;
    }

    public function exact(string $exactPath): self
    {
        $this->exactPath = $exactPath;
        return $this;
    }

    public function methods(string ...$methods): self
    {
        $this->methods = array_map('strtoupper', $methods);
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function timeout(int $timeoutMs): self
    {
        $this->timeoutMs = max(1, $timeoutMs);
        return $this;
    }

    public function split(TrafficSplitter $splitter): self
    {
        $this->splitter = $splitter;
        return $this;
    }

    public function fault(FaultInjector $faultInjector): self
    {
        $this->faultInjector = $faultInjector;
        return $this;
    }

    public function retry(RetryPolicy $retryPolicy): self
    {
        $this->retryPolicy = $retryPolicy;
        return $this;
    }

    public function enableCircuitBreaker(bool $enabled = true): self
    {
        $this->circuitBreaker = $enabled;
        return $this;
    }

    public function matches(MeshRequestContext $ctx): bool
    {
        // 1. Service name match
        if ($this->serviceName !== '*' && $this->serviceName !== $ctx->getTargetService()) {
            return false;
        }

        // 2. HTTP Method match
        if (!empty($this->methods) && !in_array('*', $this->methods, true)) {
            if (!in_array(strtoupper($ctx->getMethod()), $this->methods, true)) {
                return false;
            }
        }

        // 3. Exact Path match
        if ($this->exactPath !== null && $ctx->getPath() !== $this->exactPath) {
            return false;
        }

        // 4. Path Prefix match
        if ($this->pathPrefix !== null && !str_starts_with($ctx->getPath(), $this->pathPrefix)) {
            return false;
        }

        // 5. Header match
        foreach ($this->headers as $hKey => $hVal) {
            if ($ctx->getHeader($hKey) !== $hVal) {
                return false;
            }
        }

        return true;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getTimeoutMs(): int
    {
        return $this->timeoutMs;
    }

    public function getSplitter(): ?TrafficSplitter
    {
        return $this->splitter;
    }

    public function getFaultInjector(): ?FaultInjector
    {
        return $this->faultInjector;
    }

    public function getRetryPolicy(): ?RetryPolicy
    {
        return $this->retryPolicy;
    }

    public function isCircuitBreakerEnabled(): bool
    {
        return $this->circuitBreaker;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'service_name' => $this->serviceName,
            'path_prefix' => $this->pathPrefix,
            'exact_path' => $this->exactPath,
            'methods' => $this->methods,
            'timeout_ms' => $this->timeoutMs,
            'has_splitter' => $this->splitter !== null,
            'has_fault_injector' => $this->faultInjector !== null,
            'has_retry_policy' => $this->retryPolicy !== null,
            'circuit_breaker' => $this->circuitBreaker,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
