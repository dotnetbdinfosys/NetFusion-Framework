<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/ScheduleRunCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Executes all scheduled tasks and cron jobs that are due to run.
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
use NetFusion\Core\Console\Kernel;
use NetFusion\Core\Console\Scheduling\Schedule;

/**
 * NetFusion Schedule Run Command
 *
 * Designed to be invoked once every minute via server crontab or Task Scheduler.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class ScheduleRunCommand extends Command
{
    protected string $name = 'schedule:run';
    protected string $description = 'Run scheduled console commands and tasks';

    public function handle(): int
    {
        $schedule = new Schedule($this->app);

        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $kernel->schedule($schedule);

        $dueEvents = $schedule->dueEvents();

        if (empty($dueEvents)) {
            $this->info("No scheduled tasks are due to run at this time.");
            return 0;
        }

        $this->info("Running [" . count($dueEvents) . "] scheduled task(s)...");

        foreach ($dueEvents as $event) {
            $summary = $event->getSummary();
            $this->line("Executing: <comment>{$summary}</comment>");
            $start = microtime(true);

            $executed = $event->run();
            $elapsed = round((microtime(true) - $start) * 1000, 2);

            if ($executed) {
                $this->success("✔ Completed: {$summary} ({$elapsed}ms)");
            } else {
                $this->line("Skipped (already running / locked): {$summary}");
            }
        }

        return 0;
    }
}
