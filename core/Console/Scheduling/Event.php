<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Scheduling/Event.php
| Module    : Core\Console\Scheduling
| Namespace : NetFusion\Core\Console\Scheduling
| Purpose   : Fluent scheduled task event with frequency builder and execution lifecycle.
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
use DateTime;
use DateTimeInterface;
use NetFusion\Core\Application;
use Throwable;

/**
 * NetFusion Scheduled Event
 *
 * Represents an individual scheduled task with frequency DSL and execution handlers.
 *
 * @package NetFusion\Core\Console\Scheduling
 * @version 1.0.0
 */
class Event
{
    /**
     * Cron expression defining execution frequency.
     */
    protected string $expression = '* * * * *';

    /**
     * Optional filter callback that must evaluate to true.
     */
    protected ?Closure $filter = null;

    /**
     * Optional rejection callback that must not evaluate to true.
     */
    protected ?Closure $reject = null;

    /**
     * Prevent overlapping runs toggle.
     */
    public bool $withoutOverlapping = false;

    /**
     * Lock expiration in minutes.
     */
    public int $expiresAt = 1440;

    /**
     * Callbacks invoked before execution.
     *
     * @var array<Closure>
     */
    protected array $beforeCallbacks = [];

    /**
     * Callbacks invoked after execution.
     *
     * @var array<Closure>
     */
    protected array $afterCallbacks = [];

    /**
     * Human-readable description.
     */
    public ?string $description = null;

    /**
     * @param Application     $app
     * @param EventMutex      $mutex
     * @param string|Closure  $action Shell command string or PHP Closure.
     * @param string          $type   'command', 'exec', or 'call'
     */
    public function __construct(
        protected readonly Application $app,
        protected readonly EventMutex  $mutex,
        protected readonly string|Closure $action,
        protected readonly string      $type = 'command',
    ) {}

    // =========================================================================
    //  Frequency Helpers (Fluent Cron DSL)
    // =========================================================================

    public function cron(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    public function everyTwoMinutes(): static
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): static
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): static
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): static
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): static
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): static
    {
        $segments = explode(':', $time);
        $hour     = (int) ($segments[0] ?? 0);
        $minute   = (int) ($segments[1] ?? 0);

        return $this->cron("{$minute} {$hour} * * *");
    }

    public function twiceDaily(int $first = 1, int $second = 13): static
    {
        return $this->cron("0 {$first},{$second} * * *");
    }

    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    public function quarterly(): static
    {
        return $this->cron('0 0 1 1-12/3 *');
    }

    public function yearly(): static
    {
        return $this->cron('0 0 1 1 *');
    }

    public function weekdays(): static
    {
        return $this->cron('* * * * 1-5');
    }

    public function weekends(): static
    {
        return $this->cron('* * * * 0,6');
    }

    // =========================================================================
    //  Constraints & Overlap Handling
    // =========================================================================

    public function when(Closure $callback): static
    {
        $this->filter = $callback;
        return $this;
    }

    public function skip(Closure $callback): static
    {
        $this->reject = $callback;
        return $this;
    }

    public function withoutOverlapping(int $expiresAt = 1440): static
    {
        $this->withoutOverlapping = true;
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function name(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function before(Closure $callback): static
    {
        $this->beforeCallbacks[] = $callback;
        return $this;
    }

    public function after(Closure $callback): static
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    // =========================================================================
    //  Evaluation & Execution
    // =========================================================================

    public function isDue(?DateTimeInterface $date = null): bool
    {
        $cron = new CronExpression($this->expression);

        if (!$cron->isDue($date)) {
            return false;
        }

        if ($this->filter !== null && !($this->filter)()) {
            return false;
        }

        if ($this->reject !== null && ($this->reject)()) {
            return false;
        }

        return true;
    }

    public function run(): bool
    {
        if ($this->withoutOverlapping && !$this->mutex->create($this, $this->expiresAt)) {
            return false;
        }

        foreach ($this->beforeCallbacks as $callback) {
            $callback();
        }

        try {
            $this->execute();
            return true;
        } finally {
            foreach ($this->afterCallbacks as $callback) {
                $callback();
            }

            if ($this->withoutOverlapping) {
                $this->mutex->forget($this);
            }
        }
    }

    protected function execute(): void
    {
        if ($this->action instanceof Closure) {
            ($this->action)();
            return;
        }

        if ($this->type === 'command') {
            /** @var \NetFusion\Core\Console\Kernel $kernel */
            $kernel = $this->app->make(\NetFusion\Core\Console\Kernel::class);
            $parts = preg_split('/\s+/', (string)$this->action);
            if ($parts) {
                array_unshift($parts, 'netfusion');
                $kernel->handle($parts);
            }
            return;
        }

        if ($this->type === 'exec') {
            exec((string)$this->action);
        }
    }

    public function mutexName(): string
    {
        if ($this->action instanceof Closure) {
            return 'closure_' . md5($this->expression . ($this->description ?? ''));
        }

        return 'cmd_' . md5((string)$this->action);
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getSummary(): string
    {
        if ($this->description) {
            return $this->description;
        }

        if ($this->action instanceof Closure) {
            return 'Closure task';
        }

        return (string) $this->action;
    }
}
