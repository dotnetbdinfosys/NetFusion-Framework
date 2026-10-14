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
 * High-Performance Pre-Warmed Isolate Pool Manager.
 * Enables sub-millisecond cold starts, recycling, and auto-scaling up to max capacity.
 */
class IsolatePool
{
    /** @var array<string, Isolate> */
    protected array $pool = [];
    protected int $minSpare;
    protected int $maxCapacity;
    protected int $defaultMemoryMb;
    protected int $defaultCpuTimeoutMs;
    protected int $maxInvocationsPerIsolate;

    public function __construct(
        int $minSpare = 2,
        int $maxCapacity = 32,
        int $defaultMemoryMb = 128,
        int $defaultCpuTimeoutMs = 50,
        int $maxInvocationsPerIsolate = 1000
    ) {
        $this->minSpare = $minSpare;
        $this->maxCapacity = $maxCapacity;
        $this->defaultMemoryMb = $defaultMemoryMb;
        $this->defaultCpuTimeoutMs = $defaultCpuTimeoutMs;
        $this->maxInvocationsPerIsolate = $maxInvocationsPerIsolate;

        $this->warmUp($this->minSpare);
    }

    /**
     * Pre-warm a set number of isolates.
     */
    public function warmUp(int $count): void
    {
        $needed = min($count, $this->maxCapacity - count($this->pool));
        for ($i = 0; $i < $needed; $i++) {
            $isolate = new Isolate($this->defaultMemoryMb, $this->defaultCpuTimeoutMs);
            $this->pool[$isolate->getId()] = $isolate;
        }
    }

    /**
     * Acquire an idle isolate from the pool or spawn a new one.
     *
     * @throws IsolateResourceExceededException
     */
    public function acquire(?int $customMemoryMb = null, ?int $customCpuMs = null): Isolate
    {
        // 1. Look for an available IDLE isolate
        foreach ($this->pool as $isolate) {
            if ($isolate->isAvailable()) {
                $isolate->reserve();
                return $isolate;
            }
        }

        // 2. If under max capacity, provision a new one
        if (count($this->pool) < $this->maxCapacity) {
            $newIsolate = new Isolate(
                $customMemoryMb ?? $this->defaultMemoryMb,
                $customCpuMs ?? $this->defaultCpuTimeoutMs
            );
            $newIsolate->reserve();
            $this->pool[$newIsolate->getId()] = $newIsolate;
            return $newIsolate;
        }

        throw new IsolateResourceExceededException(sprintf(
            "Isolate pool exhausted. All %d isolates are currently busy.",
            $this->maxCapacity
        ));
    }

    /**
     * Release an isolate back to the pool.
     */
    public function release(Isolate $isolate): void
    {
        if ($isolate->getState() === IsolateState::TRIPPED || $isolate->getInvocationCount() >= $this->maxInvocationsPerIsolate) {
            $isolate->terminate();
            unset($this->pool[$isolate->getId()]);

            // Maintain spare capacity if below minimum
            if ($this->getIdleCount() < $this->minSpare) {
                $this->warmUp(1);
            }
            return;
        }

        $isolate->reset();
    }

    public function getIdleCount(): int
    {
        $idle = 0;
        foreach ($this->pool as $isolate) {
            if ($isolate->isAvailable()) {
                $idle++;
            }
        }
        return $idle;
    }

    public function getActiveCount(): int
    {
        $busy = 0;
        foreach ($this->pool as $isolate) {
            if ($isolate->getState() === IsolateState::BUSY) {
                $busy++;
            }
        }
        return $busy;
    }

    public function getTotalCount(): int
    {
        return count($this->pool);
    }

    /**
     * @return array{total: int, idle: int, active: int, maxCapacity: int}
     */
    public function stats(): array
    {
        return [
            'total' => $this->getTotalCount(),
            'idle' => $this->getIdleCount(),
            'active' => $this->getActiveCount(),
            'maxCapacity' => $this->maxCapacity,
        ];
    }
}
