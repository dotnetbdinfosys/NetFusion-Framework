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

namespace NetFusion\Core\Edge\Worker;

use NetFusion\Core\Edge\Enums\WorkerType;

/**
 * Serverless Worker Script Definition.
 * Represents polyglot executable logic (PHP callable or compiled WebAssembly bytecode) with environment bindings.
 */
class WorkerScript
{
    protected string $name;
    protected WorkerType $type;
    /** @var callable|string Binary wasm string or PHP callable */
    protected $handler;
    /** @var array<string, string> Environment variables / KV bindings */
    protected array $env;
    protected int $memoryLimitMb;
    protected int $cpuTimeoutMs;
    protected int $version;

    /**
     * @param callable|string $handler
     * @param array<string, string> $env
     */
    public function __construct(
        string $name,
        $handler,
        WorkerType $type = WorkerType::PHP,
        array $env = [],
        int $memoryLimitMb = 128,
        int $cpuTimeoutMs = 50,
        int $version = 1
    ) {
        $this->name = $name;
        $this->handler = $handler;
        $this->type = $type;
        $this->env = $env;
        $this->memoryLimitMb = $memoryLimitMb;
        $this->cpuTimeoutMs = $cpuTimeoutMs;
        $this->version = $version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): WorkerType
    {
        return $this->type;
    }

    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @return array<string, string>
     */
    public function getEnv(): array
    {
        return $this->env;
    }

    public function getEnvVar(string $key, ?string $default = null): ?string
    {
        return $this->env[$key] ?? $default;
    }

    public function setEnvVar(string $key, string $value): self
    {
        $this->env[$key] = $value;
        return $this;
    }

    public function getMemoryLimitMb(): int
    {
        return $this->memoryLimitMb;
    }

    public function getCpuTimeoutMs(): int
    {
        return $this->cpuTimeoutMs;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function isWasm(): bool
    {
        return $this->type === WorkerType::WASM;
    }
}
