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

/**
 * Per-Request Serverless Execution Context (Cloudflare Workers Specification).
 * Provides `waitUntil` async background task queue and pass-through exception flags.
 */
class ExecutionContext
{
    /** @var array<callable> */
    protected array $waitUntilTasks = [];
    protected bool $passThroughOnException = false;

    /**
     * Extend the lifetime of the event until the async task completes.
     * Allows background logging, analytics emission, or cache writes without delaying response.
     */
    public function waitUntil(callable $task): void
    {
        $this->waitUntilTasks[] = $task;
    }

    /**
     * Prevents runtime exceptions from failing the response, passing through to origin.
     */
    public function passThroughOnException(): void
    {
        $this->passThroughOnException = true;
    }

    public function shouldPassThroughOnException(): bool
    {
        return $this->passThroughOnException;
    }

    /**
     * Execute all queued waitUntil background tasks.
     *
     * @return int Number of executed background tasks
     */
    public function drain(): int
    {
        $count = count($this->waitUntilTasks);
        foreach ($this->waitUntilTasks as $task) {
            try {
                $task();
            } catch (\Throwable) {
                // Background tasks should not interrupt each other
            }
        }
        $this->waitUntilTasks = [];
        return $count;
    }

    public function getPendingCount(): int
    {
        return count($this->waitUntilTasks);
    }
}
