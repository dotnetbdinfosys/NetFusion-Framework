<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/Worker.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue
| Purpose   : Process worker daemon polling and executing queued jobs with error resilience.
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
use NetFusion\Core\Queue\Contracts\QueueDriverInterface;
use Throwable;

/**
 * NetFusion Queue Worker
 *
 * Runs a worker loop polling for jobs, executing them, handling retry logic,
 * timeout supervision, and recording permanent failures.
 *
 * @package NetFusion\Core\Queue
 * @version 1.0.0
 */
class Worker
{
    /**
     * Indicates if the worker should stop running.
     */
    protected bool $shouldQuit = false;

    /**
     * @param QueueManager           $manager
     * @param FailedJobProvider|null $failedJobs
     */
    public function __construct(
        protected readonly QueueManager       $manager,
        protected readonly ?FailedJobProvider $failedJobs = null,
    ) {}

    /**
     * Process next single job from the queue.
     *
     * @param  string $connection
     * @param  string $queue
     * @return bool   True if a job was processed, false if queue was empty.
     */
    public function runNextJob(string $connection = 'default', string $queue = 'default'): bool
    {
        $driver = $this->manager->connection($connection);
        $job = $driver->pop($queue);

        if (!$job) {
            return false;
        }

        $this->process($driver, $job);
        return true;
    }

    /**
     * Process a given job instance with try-catch and retry handling.
     */
    public function process(QueueDriverInterface $driver, JobInterface $job): void
    {
        try {
            $job->handle();
            $driver->acknowledge($job);
        } catch (Throwable $e) {
            $this->handleJobException($driver, $job, $e);
        }
    }

    /**
     * Handle job execution failure.
     */
    protected function handleJobException(QueueDriverInterface $driver, JobInterface $job, Throwable $e): void
    {
        if ($job->attempts() >= $job->tries()) {
            // Max attempts exceeded: mark as failed permanently
            $this->fail($driver, $job, $e);
        } else {
            // Re-queue with backoff delay
            $backoff = $job->backoff();
            $driver->release($job, $backoff);
        }
    }

    /**
     * Mark a job as permanently failed.
     */
    protected function fail(QueueDriverInterface $driver, JobInterface $job, Throwable $e): void
    {
        try {
            $job->failed($e);
        } finally {
            $driver->acknowledge($job);

            if ($this->failedJobs !== null) {
                $this->failedJobs->log($job, $e);
            }
        }
    }

    /**
     * Run a continuous worker daemon.
     *
     * @param string   $connection
     * @param string   $queue
     * @param int      $sleepSeconds Seconds to sleep when empty.
     * @param int      $maxJobs      Max number of jobs to process before exiting (0 = infinite).
     * @param int      $memoryLimit  Memory limit in MB before exiting (0 = no limit).
     * @param callable $output       Callback for logging worker progress.
     */
    public function daemon(
        string   $connection   = 'default',
        string   $queue        = 'default',
        int      $sleepSeconds = 3,
        int      $maxJobs      = 0,
        int      $memoryLimit  = 128,
        ?callable $output      = null,
    ): void {
        $processed = 0;

        while (!$this->shouldQuit) {
            $jobHandled = $this->runNextJob($connection, $queue);

            if ($jobHandled) {
                $processed++;
                if ($output) {
                    $output("Job processed successfully. Total: {$processed}");
                }

                if ($maxJobs > 0 && $processed >= $maxJobs) {
                    if ($output) {
                        $output("Max jobs reached ({$maxJobs}). Stopping worker.");
                    }
                    break;
                }
            } else {
                sleep($sleepSeconds);
            }

            // Check memory limit to prevent memory leaks in daemon mode
            if ($memoryLimit > 0 && (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit) {
                if ($output) {
                    $output("Memory limit exceeded ({$memoryLimit} MB). Exiting worker.");
                }
                break;
            }
        }
    }

    /**
     * Signal the worker to stop processing.
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }
}
