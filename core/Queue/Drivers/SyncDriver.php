<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/Drivers/SyncDriver.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue\Drivers
| Purpose   : Synchronous queue driver executing jobs immediately upon push.
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

namespace NetFusion\Core\Queue\Drivers;

use NetFusion\Core\Queue\Contracts\JobInterface;
use NetFusion\Core\Queue\Contracts\QueueDriverInterface;
use Throwable;

/**
 * NetFusion Sync Queue Driver
 *
 * Runs the pushed job immediately on the current process/thread.
 * Ideal for local development, unit tests, and quick scripting.
 *
 * @package NetFusion\Core\Queue\Drivers
 * @version 1.0.0
 */
class SyncDriver implements QueueDriverInterface
{
    /**
     * {@inheritDoc}
     */
    public function push(JobInterface|string $job, string $queue = 'default'): string
    {
        $jobInstance = is_string($job) ? unserialize($job) : $job;

        $id = bin2hex(random_bytes(16));
        $jobInstance->setJobId($id);
        $jobInstance->onQueue($queue);
        $jobInstance->incrementAttempts();

        try {
            $jobInstance->handle();
        } catch (Throwable $e) {
            $jobInstance->failed($e);
            throw $e;
        }

        return $id;
    }

    /**
     * {@inheritDoc}
     */
    public function later(int $delay, JobInterface|string $job, string $queue = 'default'): string
    {
        // For sync driver, delay is simulated or immediate
        if ($delay > 0 && php_sapi_name() === 'cli') {
            // No sleep in HTTP requests to prevent timeout
        }
        return $this->push($job, $queue);
    }

    /**
     * {@inheritDoc}
     */
    public function pop(string $queue = 'default'): ?JobInterface
    {
        // Jobs are handled immediately, so pop is always empty
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function acknowledge(JobInterface $job): void
    {
        // No-op for sync driver
    }

    /**
     * {@inheritDoc}
     */
    public function release(JobInterface $job, int $delay = 0): void
    {
        // Re-execute immediately
        $this->push($job, $job->getQueue());
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function purge(string $queue = 'default'): int
    {
        return 0;
    }
}
