<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Scheduling/Schedule.php
| Module    : Core\Console\Scheduling
| Namespace : NetFusion\Core\Console\Scheduling
| Purpose   : Central task scheduler builder registering command, closure, and exec events.
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

namespace NetFusion\Core\Console\Scheduling;

use Closure;
use DateTimeInterface;
use NetFusion\Core\Application;

/**
 * NetFusion Schedule Builder
 *
 * Central registry for defining scheduled commands and recurring jobs.
 *
 * @package NetFusion\Core\Console\Scheduling
 * @version 1.0.0
 */
class Schedule
{
    /**
     * Registered scheduled events.
     *
     * @var array<int, Event>
     */
    protected array $events = [];

    /**
     * Mutex provider instance.
     */
    protected EventMutex $mutex;

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app,
    ) {
        $this->mutex = new EventMutex($app);
    }

    /**
     * Schedule a NetFusion console command.
     *
     * @param  string $command e.g. 'queue:work --once' or 'cache:clear'
     * @return Event
     */
    public function command(string $command): Event
    {
        $event = new Event($this->app, $this->mutex, $command, 'command');
        $this->events[] = $event;
        return $event;
    }

    /**
     * Schedule a PHP Closure task.
     *
     * @param  Closure $callback
     * @return Event
     */
    public function call(Closure $callback): Event
    {
        $event = new Event($this->app, $this->mutex, $callback, 'call');
        $this->events[] = $event;
        return $event;
    }

    /**
     * Schedule a raw shell/system command.
     *
     * @param  string $command
     * @return Event
     */
    public function exec(string $command): Event
    {
        $event = new Event($this->app, $this->mutex, $command, 'exec');
        $this->events[] = $event;
        return $event;
    }

    /**
     * Get all registered scheduled events.
     *
     * @return array<int, Event>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Filter events that are due to execute right now.
     *
     * @param  DateTimeInterface|null $date
     * @return array<int, Event>
     */
    public function dueEvents(?DateTimeInterface $date = null): array
    {
        return array_values(array_filter(
            $this->events,
            fn (Event $event) => $event->isDue($date)
        ));
    }
}
