<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/Contracts/JobInterface.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue\Contracts
| Purpose   : Interface contract for all executable background jobs.
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

namespace NetFusion\Core\Queue\Contracts;

use Throwable;

/**
 * NetFusion Queue Job Interface
 *
 * Defines execution, lifecycle, and retry parameters for background tasks.
 *
 * @package NetFusion\Core\Queue\Contracts
 * @version 1.0.0
 */
interface JobInterface
{
    /**
     * Execute the job logic.
     */
    public function handle(): void;

    /**
     * Handle job failure after max retry attempts are exhausted.
     *
     * @param Throwable $exception The exception that caused failure.
     */
    public function failed(Throwable $exception): void;

    /**
     * Maximum number of attempts allowed for this job.
     */
    public function tries(): int;

    /**
     * Number of seconds to wait before retrying a failed job.
     */
    public function backoff(): int;

    /**
     * Maximum execution time in seconds allowed for this job.
     */
    public function timeout(): int;

    /**
     * Get unique job identifier.
     */
    public function getJobId(): ?string;

    /**
     * Set unique job identifier.
     */
    public function setJobId(string $id): static;

    /**
     * Get the number of times the job has been attempted.
     */
    public function attempts(): int;

    /**
     * Increment the attempt count.
     */
    public function incrementAttempts(): int;

    /**
     * Get queue name this job belongs to.
     */
    public function getQueue(): string;

    /**
     * Set queue name this job belongs to.
     */
    public function onQueue(string $queue): static;
}
