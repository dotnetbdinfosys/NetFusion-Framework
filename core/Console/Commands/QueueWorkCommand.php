<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/QueueWorkCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Starts the queue worker daemon process to process queued jobs.
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
use NetFusion\Core\Queue\Worker;

/**
 * NetFusion Queue Work Command
 *
 * Runs a continuous or one-off queue worker daemon.
 * Options:
 *  --connection=name
 *  --queue=name
 *  --once (process single job and exit)
 *  --max-jobs=N
 *  --sleep=seconds
 *  --memory=MB
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class QueueWorkCommand extends Command
{
    protected string $name = 'queue:work';
    protected string $description = 'Start processing jobs on the queue as a daemon';

    public function handle(): int
    {
        $connection = (string) ($this->option('connection') ?: 'default');
        $queue      = (string) ($this->option('queue') ?: 'default');
        $once       = (bool) $this->option('once', false);
        $sleep      = (int) ($this->option('sleep') ?: 3);
        $maxJobs    = (int) ($this->option('max-jobs') ?: 0);
        $memory     = (int) ($this->option('memory') ?: 128);

        $this->info("Starting NetFusion Queue Worker [Connection: {$connection}] [Queue: {$queue}]...");

        /** @var QueueManager $queueManager */
        $queueManager = $this->app->make('queue');
        /** @var FailedJobProvider $failedJobs */
        $failedJobs = $this->app->make('queue.failed');

        $worker = new Worker($queueManager, $failedJobs);

        if ($once) {
            $processed = $worker->runNextJob($connection, $queue);
            if ($processed) {
                $this->success("Processed single job successfully.");
            } else {
                $this->line("No jobs available on queue [{$queue}].");
            }
            return 0;
        }

        $worker->daemon(
            connection: $connection,
            queue: $queue,
            sleepSeconds: $sleep,
            maxJobs: $maxJobs,
            memoryLimit: $memory,
            output: fn(string $msg) => $this->line("[" . date('Y-m-d H:i:s') . "] " . $msg),
        );

        return 0;
    }
}
