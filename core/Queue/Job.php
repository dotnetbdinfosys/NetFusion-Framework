<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/Job.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue
| Purpose   : Abstract base class for all background jobs with lifecycle management.
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

namespace NetFusion\Core\Queue;

use NetFusion\Core\Queue\Contracts\JobInterface;
use Throwable;

/**
 * NetFusion Base Job
 *
 * Provides default implementation of job lifecycle, serialization,
 * retry limits, and queue routing.
 *
 * @package NetFusion\Core\Queue
 * @version 1.0.0
 */
abstract class Job implements JobInterface
{
    /**
     * Unique job ID.
     */
    protected ?string $jobId = null;

    /**
     * Target queue name.
     */
    protected string $queue = 'default';

    /**
     * Number of times this job has been attempted.
     */
    protected int $attempts = 0;

    /**
     * Maximum allowed attempts.
     */
    public int $tries = 3;

    /**
     * Backoff delay in seconds between retries.
     */
    public int $backoff = 5;

    /**
     * Max execution timeout in seconds.
     */
    public int $timeout = 60;

    /**
     * {@inheritDoc}
     */
    public function failed(Throwable $exception): void
    {
        // Default: no-op, child classes can override for cleanup or alerting.
    }

    /**
     * {@inheritDoc}
     */
    public function tries(): int
    {
        return $this->tries;
    }

    /**
     * {@inheritDoc}
     */
    public function backoff(): int
    {
        return $this->backoff;
    }

    /**
     * {@inheritDoc}
     */
    public function timeout(): int
    {
        return $this->timeout;
    }

    /**
     * {@inheritDoc}
     */
    public function getJobId(): ?string
    {
        return $this->jobId;
    }

    /**
     * {@inheritDoc}
     */
    public function setJobId(string $id): static
    {
        $this->jobId = $id;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * {@inheritDoc}
     */
    public function incrementAttempts(): int
    {
        return ++$this->attempts;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * {@inheritDoc}
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Dispatch this job to the queue helper.
     *
     * @param  int $delay Optional delay in seconds.
     * @return string     Unique job ID.
     */
    public function dispatch(int $delay = 0): string
    {
        /** @var QueueManager $queueManager */
        $queueManager = app('queue');
        return $delay > 0
            ? $queueManager->later($delay, $this, $this->queue)
            : $queueManager->push($this, $this->queue);
    }
}
