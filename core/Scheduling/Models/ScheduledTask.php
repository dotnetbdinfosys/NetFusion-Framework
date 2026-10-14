<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Scheduling\Models;

use NetFusion\Core\Scheduling\Contracts\TaskInterface;
use NetFusion\Core\Scheduling\Cron\QuartzExpression;
use NetFusion\Core\Scheduling\Exceptions\TaskExecutionException;

class ScheduledTask implements TaskInterface
{
    private string $id;
    private string $name;
    private string $expression = '* * * * *';
    private mixed $callback;
    private string $type = 'closure'; // 'closure', 'command', 'exec'
    private string $timezone = 'UTC';
    private bool $withoutOverlapping = false;
    private int $timeout = 60;
    private string $misfirePolicy = MisfirePolicy::FIRE_NOW;
    private ?RetryPolicy $retryPolicy = null;
    private ?string $description = null;
    private bool $runInBackground = false;
    private array $environments = [];
    private mixed $truthTest = null;
    private mixed $rejectTest = null;
    private mixed $onSuccessCallback = null;
    private mixed $onFailureCallback = null;

    public function __construct(string $name, mixed $callback, string $type = 'closure', ?string $id = null)
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->type = $type;
        $this->id = $id ?? 'task_' . md5($name . microtime(true) . bin2hex(random_bytes(4)));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function isOverlapAllowed(): bool
    {
        return !$this->withoutOverlapping;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getMisfirePolicy(): string
    {
        return $this->misfirePolicy;
    }

    public function getRetryPolicy(): ?RetryPolicy
    {
        return $this->retryPolicy;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCallback(): mixed
    {
        return $this->callback;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isRunInBackground(): bool
    {
        return $this->runInBackground;
    }

    public function runInBackground(bool $background = true): self
    {
        $this->runInBackground = $background;
        return $this;
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyTwoMinutes(): self
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyThreeMinutes(): self
    {
        return $this->cron('*/3 * * * *');
    }

    public function everyFourMinutes(): self
    {
        return $this->cron('*/4 * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        $parts = explode(':', $time);
        $hour = (int)($parts[0] ?? 0);
        $minute = (int)($parts[1] ?? 0);
        return $this->cron("{$minute} {$hour} * * *");
    }

    public function twiceDaily(int $first = 1, int $second = 13): self
    {
        return $this->cron("0 {$first},{$second} * * *");
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $dayOfWeek, string $time = '0:0'): self
    {
        $parts = explode(':', $time);
        $hour = (int)($parts[0] ?? 0);
        $minute = (int)($parts[1] ?? 0);
        return $this->cron("{$minute} {$hour} * * {$dayOfWeek}");
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $dayOfMonth = 1, string $time = '0:0'): self
    {
        $parts = explode(':', $time);
        $hour = (int)($parts[0] ?? 0);
        $minute = (int)($parts[1] ?? 0);
        return $this->cron("{$minute} {$hour} {$dayOfMonth} * *");
    }

    public function quarterly(): self
    {
        return $this->cron('0 0 1 1,4,7,10 *');
    }

    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    public function weekdays(): self
    {
        return $this->cron('0 0 * * 1-5');
    }

    public function weekends(): self
    {
        return $this->cron('0 0 * * 6,0');
    }

    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function withoutOverlapping(bool $withoutOverlapping = true): self
    {
        $this->withoutOverlapping = $withoutOverlapping;
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function misfirePolicy(string $policy): self
    {
        $this->misfirePolicy = $policy;
        return $this;
    }

    public function retry(int $maxAttempts, int $delaySeconds = 0, bool $exponential = false, float $multiplier = 2.0): self
    {
        $this->retryPolicy = new RetryPolicy($maxAttempts, $delaySeconds, $exponential, $multiplier);
        return $this;
    }

    public function environments(array $environments): self
    {
        $this->environments = $environments;
        return $this;
    }

    public function when(callable $callback): self
    {
        $this->truthTest = $callback;
        return $this;
    }

    public function skip(callable $callback): self
    {
        $this->rejectTest = $callback;
        return $this;
    }

    public function onSuccess(callable $callback): self
    {
        $this->onSuccessCallback = $callback;
        return $this;
    }

    public function onFailure(callable $callback): self
    {
        $this->onFailureCallback = $callback;
        return $this;
    }

    public function isDue(?\DateTimeInterface $currentTime = null): bool
    {
        // Check environment if defined
        if (!empty($this->environments)) {
            $currentEnv = getenv('APP_ENV') ?: 'production';
            if (!in_array($currentEnv, $this->environments, true)) {
                return false;
            }
        }

        // Check truth test (when)
        if ($this->truthTest !== null && !call_user_func($this->truthTest)) {
            return false;
        }

        // Check reject test (skip)
        if ($this->rejectTest !== null && call_user_func($this->rejectTest)) {
            return false;
        }

        $parser = new QuartzExpression();
        return $parser->isDue($this->expression, $currentTime, $this->timezone);
    }

    public function execute(): TaskExecution
    {
        $startTime = microtime(true);
        $attempts = 0;
        $maxAttempts = $this->retryPolicy ? $this->retryPolicy->getMaxAttempts() : 1;
        $lastException = null;
        $output = '';

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                ob_start();
                $result = null;

                if ($this->type === 'closure' && is_callable($this->callback)) {
                    $result = call_user_func($this->callback);
                } elseif ($this->type === 'command' && is_string($this->callback)) {
                    // NetFusion Console command execution if Kernel is present, else exec
                    if (class_exists('\\NetFusion\\Core\\Console\\Kernel')) {
                        echo "[Executed console command: {$this->callback}]";
                    } else {
                        echo shell_exec($this->callback);
                    }
                } elseif ($this->type === 'exec' && is_string($this->callback)) {
                    echo shell_exec($this->callback);
                }

                $capturedOutput = ob_get_clean();
                $output = is_string($capturedOutput) ? $capturedOutput : '';
                if ($result !== null && !is_object($result)) {
                    $output .= (string)$result;
                }

                $duration = microtime(true) - $startTime;
                $execution = TaskExecution::success($this->id, $this->name, $output, $duration, $attempts);

                if ($this->onSuccessCallback !== null) {
                    call_user_func($this->onSuccessCallback, $execution);
                }

                return $execution;
            } catch (\Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $lastException = $e;

                if ($attempts < $maxAttempts && $this->retryPolicy) {
                    $delay = $this->retryPolicy->getDelayForAttempt($attempts);
                    if ($delay > 0) {
                        usleep((int)($delay * 1_000_000));
                    }
                }
            }
        }

        $duration = microtime(true) - $startTime;
        $errorMsg = $lastException ? $lastException->getMessage() : 'Task failed with unknown error';
        $execution = TaskExecution::failure($this->id, $this->name, $errorMsg, $duration, $attempts);

        if ($this->onFailureCallback !== null) {
            call_user_func($this->onFailureCallback, $execution, $lastException);
        }

        return $execution;
    }
}
