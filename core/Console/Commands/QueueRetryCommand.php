<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/QueueRetryCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Retries one or all permanently failed queue jobs by pushing them back onto queue.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Queue\FailedJobProvider;
use NetFusion\Core\Queue\QueueManager;

/**
 * NetFusion Queue Retry Command
 *
 * Retries a failed job by ID or all failed jobs.
 * Usage:
 *   php netfusion queue:retry {id}
 *   php netfusion queue:retry all
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class QueueRetryCommand extends Command
{
    protected string $name = 'queue:retry';
    protected string $description = 'Retry a failed queue job by ID or retry all failed jobs';

    public function handle(): int
    {
        $target = $this->argument(0);

        if (!$target) {
            $this->error("Job ID or 'all' required. Example: php netfusion queue:retry all");
            return 1;
        }

        /** @var FailedJobProvider $failedJobs */
        $failedJobs = $this->app->make('queue.failed');
        /** @var QueueManager $queueManager */
        $queueManager = $this->app->make('queue');

        if (strtolower($target) === 'all') {
            $jobs = $failedJobs->all();
            if (empty($jobs)) {
                $this->info("No failed jobs to retry.");
                return 0;
            }

            foreach ($jobs as $job) {
                $this->retryJob($failedJobs, $queueManager, $job);
            }

            $this->success("Retried " . count($jobs) . " failed job(s).");
            return 0;
        }

        $job = $failedJobs->find($target);
        if (!$job) {
            $this->error("Failed job [{$target}] not found.");
            return 1;
        }

        $this->retryJob($failedJobs, $queueManager, $job);
        $this->success("Failed job [{$target}] pushed back to queue [{$job['queue']}].");
        return 0;
    }

    protected function retryJob(FailedJobProvider $failedJobs, QueueManager $queueManager, array $jobRecord): void
    {
        $payload = unserialize($jobRecord['payload']);
        $queueManager->connection($jobRecord['connection'])->push($payload, $jobRecord['queue']);
        $failedJobs->forget($jobRecord['id']);
    }
}
