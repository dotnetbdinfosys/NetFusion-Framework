<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Scheduling/CronExpression.php
| Module    : Core\Console\Scheduling
| Namespace : NetFusion\Core\Console\Scheduling
| Purpose   : Pure PHP 5-field cron expression parser and due-time evaluator.
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

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * NetFusion Cron Expression Parser
 *
 * Evaluates standard 5-field cron specifications without any external dependencies.
 * Fields: minute (0-59), hour (0-23), day of month (1-31), month (1-12), day of week (0-7).
 *
 * @package NetFusion\Core\Console\Scheduling
 * @version 1.0.0
 */
class CronExpression
{
    /**
     * @var string[]
     */
    protected array $fields;

    /**
     * @param string $expression Standard 5-field cron string.
     */
    public function __construct(
        protected readonly string $expression,
    ) {
        $parts = preg_split('/\s+/', trim($expression));
        if ($parts === false || count($parts) !== 5) {
            throw new InvalidArgumentException("Invalid cron expression: [{$expression}]. Must contain exactly 5 fields.");
        }

        $this->fields = $parts;
    }

    /**
     * Determine if the cron expression is due to run at the given date/time.
     *
     * @param  DateTimeInterface|null $date
     * @return bool
     */
    public function isDue(?DateTimeInterface $date = null): bool
    {
        $date = $date ?? new DateTime();

        $minute     = (int) $date->format('i');
        $hour       = (int) $date->format('G');
        $dayOfMonth = (int) $date->format('j');
        $month      = (int) $date->format('n');
        $dayOfWeek  = (int) $date->format('w'); // 0 (Sunday) to 6 (Saturday)

        return $this->matchField($this->fields[0], $minute, 0, 59)
            && $this->matchField($this->fields[1], $hour, 0, 23)
            && $this->matchField($this->fields[2], $dayOfMonth, 1, 31)
            && $this->matchField($this->fields[3], $month, 1, 12)
            && $this->matchDayOfWeek($this->fields[4], $dayOfWeek);
    }

    /**
     * Check if an individual cron field matches a given integer value.
     */
    protected function matchField(string $field, int $value, int $min, int $max): bool
    {
        if ($field === '*') {
            return true;
        }

        // Comma-separated list: "1,15,30"
        if (str_contains($field, ',')) {
            foreach (explode(',', $field) as $subField) {
                if ($this->matchField(trim($subField), $value, $min, $max)) {
                    return true;
                }
            }
            return false;
        }

        // Step: "*/5" or "1-30/5"
        if (str_contains($field, '/')) {
            [$range, $step] = explode('/', $field, 2);
            $stepInt = (int) $step;
            if ($stepInt <= 0) {
                return false;
            }

            if ($range === '*') {
                return ($value % $stepInt) === 0;
            }

            if (str_contains($range, '-')) {
                [$start, $end] = explode('-', $range, 2);
                $startInt = (int) $start;
                $endInt   = (int) $end;
                return $value >= $startInt && $value <= $endInt && (($value - $startInt) % $stepInt) === 0;
            }

            return false;
        }

        // Range: "1-5"
        if (str_contains($field, '-')) {
            [$start, $end] = explode('-', $field, 2);
            return $value >= (int) $start && $value <= (int) $end;
        }

        // Exact match: "15"
        return $value === (int) $field;
    }

    /**
     * Check day of week field (0 and 7 are both Sunday).
     */
    protected function matchDayOfWeek(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        // In standard cron, 7 is also Sunday (0)
        if ($value === 0 && ($this->matchField($field, 0, 0, 7) || $this->matchField($field, 7, 0, 7))) {
            return true;
        }

        return $this->matchField($field, $value, 0, 7);
    }

    /**
     * Get raw expression string.
     */
    public function getExpression(): string
    {
        return $this->expression;
    }
}
