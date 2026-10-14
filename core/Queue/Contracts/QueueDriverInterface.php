<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/Contracts/QueueDriverInterface.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue\Contracts
| Purpose   : Interface contract for queue storage and retrieval drivers.
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

/**
 * NetFusion Queue Driver Contract
 *
 * Defines methods required to push, delay, retrieve, and delete queued jobs.
 *
 * @package NetFusion\Core\Queue\Contracts
 * @version 1.0.0
 */
interface QueueDriverInterface
{
    /**
     * Push a new job onto the queue.
     *
     * @param  JobInterface|string $job   The job instance or serialized string.
     * @param  string              $queue Specific queue name.
     * @return string                     Unique job identifier.
     */
    public function push(JobInterface|string $job, string $queue = 'default'): string;

    /**
     * Push a new job onto the queue with a delay.
     *
     * @param  int                 $delay Number of seconds to delay execution.
     * @param  JobInterface|string $job   The job instance or serialized string.
     * @param  string              $queue Specific queue name.
     * @return string                     Unique job identifier.
     */
    public function later(int $delay, JobInterface|string $job, string $queue = 'default'): string;

    /**
     * Pop the next available job off of the queue.
     *
     * @param  string            $queue Specific queue name.
     * @return JobInterface|null        Job instance or null if queue is empty.
     */
    public function pop(string $queue = 'default'): ?JobInterface;

    /**
     * Acknowledge and remove a finished job from the queue.
     *
     * @param JobInterface $job
     */
    public function acknowledge(JobInterface $job): void;

    /**
     * Release a failed or reserved job back into the queue for a retry.
     *
     * @param JobInterface $job
     * @param int          $delay Delay in seconds before it becomes available again.
     */
    public function release(JobInterface $job, int $delay = 0): void;

    /**
     * Get the size (number of pending jobs) in a given queue.
     *
     * @param  string $queue
     * @return int
     */
    public function size(string $queue = 'default'): int;

    /**
     * Purge all jobs from a given queue.
     *
     * @param  string $queue
     * @return int Number of purged jobs.
     */
    public function purge(string $queue = 'default'): int;
}
