<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/ScheduleListCommand.php
| Module    : Core\Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Lists all registered scheduled tasks, cron expressions, and descriptions.
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
 * NetFusion Schedule List Command
 *
 * Displays a formatted table of all scheduled tasks registered in the framework.
 *
 * @package NetFusion\Core\Console\Commands
 * @version 1.0.0
 */
class ScheduleListCommand extends Command
{
    protected string $name = 'schedule:list';
    protected string $description = 'List all registered scheduled tasks and frequencies';

    public function handle(): int
    {
        $schedule = new Schedule($this->app);

        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $kernel->schedule($schedule);

        $events = $schedule->events();

        if (empty($events)) {
            $this->info("No scheduled tasks have been registered.");
            return 0;
        }

        $this->line(str_pad("CRON EXPRESSION", 22) . str_pad("OVERLAP LOCK", 18) . "TASK / COMMAND");
        $this->line(str_repeat("-", 80));

        foreach ($events as $event) {
            $cron = str_pad($event->getExpression(), 22);
            $overlap = str_pad($event->withoutOverlapping ? "Yes ({$event->expiresAt}m)" : "No", 18);
            $task = $event->getSummary();

            $this->line("{$cron}{$overlap}{$task}");
        }

        $this->line(PHP_EOL . "Total scheduled tasks: " . count($events));

        return 0;
    }
}
