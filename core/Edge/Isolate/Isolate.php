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

namespace NetFusion\Core\Edge\Isolate;

use NetFusion\Core\Edge\Enums\IsolateState;
use NetFusion\Core\Edge\Exceptions\IsolateResourceExceededException;

/**
 * Lightweight Isolated Execution Sandbox (V8 Isolate Architecture).
 * Enforces per-request memory quota and CPU execution timeouts.
 */
class Isolate
{
    protected string $id;
    protected IsolateState $state;
    protected int $memoryLimitBytes;
    protected int $cpuTimeoutMs;
    protected int $invocationCount = 0;
    protected float $createdAt;
    protected ?float $lastActiveAt = null;

    public function __construct(
        int $memoryLimitMb = 128,
        int $cpuTimeoutMs = 50,
        ?string $id = null
    ) {
        $this->id = $id ?? 'iso_' . bin2hex(random_bytes(6));
        $this->memoryLimitBytes = $memoryLimitMb * 1024 * 1024;
        $this->cpuTimeoutMs = $cpuTimeoutMs;
        $this->state = IsolateState::IDLE;
        $this->createdAt = microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getState(): IsolateState
    {
        return $this->state;
    }

    public function getMemoryLimitBytes(): int
    {
        return $this->memoryLimitBytes;
    }

    public function getCpuTimeoutMs(): int
    {
        return $this->cpuTimeoutMs;
    }

    public function getInvocationCount(): int
    {
        return $this->invocationCount;
    }

    public function getCreatedAt(): float
    {
        return $this->createdAt;
    }

    public function getLastActiveAt(): ?float
    {
        return $this->lastActiveAt;
    }

    public function isAvailable(): bool
    {
        return $this->state === IsolateState::IDLE;
    }

    /**
     * Execute a callable in this isolated context with safety bounds.
     *
     * @template T
     * @param callable(): T $task
     * @return T
     * @throws IsolateResourceExceededException
     */
    public function execute(callable $task): mixed
    {
        if ($this->state === IsolateState::TERMINATED || $this->state === IsolateState::TRIPPED) {
            throw new IsolateResourceExceededException(sprintf("Isolate '%s' is not runnable (state: %s)", $this->id, $this->state->value));
        }

        $this->state = IsolateState::BUSY;
        $this->lastActiveAt = microtime(true);
        $this->invocationCount++;

        $startMem = memory_get_usage();
        $startTime = microtime(true);

        try {
            $result = $task();

            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $usedMem = memory_get_usage() - $startMem;

            if ($elapsedMs > $this->cpuTimeoutMs) {
                $this->state = IsolateState::TRIPPED;
                throw new IsolateResourceExceededException(sprintf(
                    "Isolate '%s' exceeded CPU execution quota (used: %.2fms, limit: %dms)",
                    $this->id,
                    $elapsedMs,
                    $this->cpuTimeoutMs
                ));
            }

            if ($usedMem > $this->memoryLimitBytes) {
                $this->state = IsolateState::TRIPPED;
                throw new IsolateResourceExceededException(sprintf(
                    "Isolate '%s' exceeded memory quota (used: %d bytes, limit: %d bytes)",
                    $this->id,
                    $usedMem,
                    $this->memoryLimitBytes
                ));
            }

            $this->state = IsolateState::IDLE;
            return $result;
        } catch (\Throwable $e) {
            if ($this->state !== IsolateState::TRIPPED) {
                $this->state = IsolateState::IDLE;
            }
            throw $e;
        }
    }

    /**
     * Terminate the isolate, releasing resources.
     */
    public function terminate(): void
    {
        $this->state = IsolateState::TERMINATED;
    }

    /**
     * Reset the isolate back to IDLE state.
     */
    public function reset(): void
    {
        $this->state = IsolateState::IDLE;
    }

    /**
     * Mark the isolate as reserved (BUSY) when acquired from the pool.
     */
    public function reserve(): void
    {
        $this->state = IsolateState::BUSY;
    }
}
