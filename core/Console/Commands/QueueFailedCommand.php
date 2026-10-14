<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/QueueFailedCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Lists all permanently failed queue jobs with error details.
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

/**
 * NetFusion Queue Failed Command
 *
 * Lists all jobs that exhausted retry limits and were logged to netfusion_failed_jobs.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class QueueFailedCommand extends Command
{
    protected string $name = 'queue:failed';
    protected string $description = 'List all permanently failed queue jobs';

    public function handle(): int
    {
        /** @var FailedJobProvider $failedJobs */
        $failedJobs = $this->app->make('queue.failed');

        $jobs = $failedJobs->all();

        if (empty($jobs)) {
            $this->info("No failed jobs found.");
            return 0;
        }

        $this->line(str_pad("ID", 34) . str_pad("QUEUE", 15) . str_pad("JOB", 25) . str_pad("FAILED AT", 22) . "EXCEPTION");
        $this->line(str_repeat("-", 110));

        foreach ($jobs as $job) {
            $id = str_pad((string)$job['id'], 34);
            $queue = str_pad((string)$job['queue'], 15);
            $jobClass = 'Unknown';
            if (!empty($job['payload'])) {
                try {
                    $unserialized = @unserialize($job['payload']);
                    if (is_object($unserialized)) {
                        $jobClass = (new \ReflectionClass($unserialized))->getShortName();
                    }
                } catch (\Throwable) {}
            }
            $jobName = str_pad($jobClass, 25);
            $failedAt = str_pad((string)$job['failed_at'], 22);
            $exception = strtok((string)$job['exception'], "\n");

            $this->line("{$id}{$queue}{$jobName}{$failedAt}{$exception}");
        }

        return 0;
    }
}
