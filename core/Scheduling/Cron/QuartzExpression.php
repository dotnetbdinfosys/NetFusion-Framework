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

namespace NetFusion\Core\Scheduling\Cron;

use NetFusion\Core\Scheduling\Contracts\CronParserInterface;
use NetFusion\Core\Scheduling\Exceptions\CronParseException;

class QuartzExpression implements CronParserInterface
{
    private static array $macros = [
        '@yearly'            => '0 0 1 1 *',
        '@annually'          => '0 0 1 1 *',
        '@monthly'           => '0 0 1 * *',
        '@weekly'            => '0 0 * * 0',
        '@daily'             => '0 0 * * *',
        '@midnight'          => '0 0 * * *',
        '@hourly'            => '0 * * * *',
        '@everyMinute'       => '* * * * *',
        '@everyTwoMinutes'   => '*/2 * * * *',
        '@everyThreeMinutes' => '*/3 * * * *',
        '@everyFiveMinutes'  => '*/5 * * * *',
        '@everyTenMinutes'   => '*/10 * * * *',
        '@everyFifteenMinutes' => '*/15 * * * *',
        '@everyThirtyMinutes' => '*/30 * * * *',
    ];

    private static array $monthMap = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4,
        'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8,
        'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    private static array $dayOfWeekMap = [
        'SUN' => 0, 'MON' => 1, 'TUE' => 2, 'WED' => 3,
        'THU' => 4, 'FRI' => 5, 'SAT' => 6, '7' => 0,
    ];

    /**
     * Check if expression is due at given time
     */
    public function isDue(string $expression, ?\DateTimeInterface $currentTime = null, string $timezone = 'UTC'): bool
    {
        $dt = $this->createDateTime($currentTime, $timezone);
        $fields = $this->parseExpression($expression);

        return $this->matchesTime($fields, $dt);
    }

    /**
     * Get the next run date matching expression
     */
    public function getNextRunDate(string $expression, ?\DateTimeInterface $currentTime = null, string $timezone = 'UTC'): \DateTimeImmutable
    {
        $dt = $this->createDateTime($currentTime, $timezone);
        $fields = $this->parseExpression($expression);

        $isSecondLevel = $fields['is_second_level'];
        $step = $isSecondLevel ? 1 : 60;

        // Clone and zero out lower granularity if minute-level
        if (!$isSecondLevel) {
            $dt = $dt->setTime((int)$dt->format('H'), (int)$dt->format('i'), 0);
            $dt = $dt->modify('+1 minute');
        } else {
            $dt = $dt->modify('+1 second');
        }

        $maxIterations = 100000;
        $iteration = 0;

        while ($iteration++ < $maxIterations) {
            if ($this->matchesTime($fields, $dt)) {
                return $dt;
            }

            // Optimization jump: if year doesn't match, jump to next year
            $year = (int)$dt->format('Y');
            if ($fields['year'] !== '*' && !$this->matchNumericField($fields['year'], $year, 1970, 2099)) {
                $dt = $dt->modify('+1 year');
                $dt = $dt->setDate((int)$dt->format('Y'), 1, 1)->setTime(0, 0, 0);
                continue;
            }

            // If month doesn't match, jump to next month
            $month = (int)$dt->format('n');
            if (!$this->matchNumericField($fields['month'], $month, 1, 12)) {
                $dt = $dt->modify('first day of next month')->setTime(0, 0, 0);
                continue;
            }

            // If day of month or day of week doesn't match, jump to next day
            if (!$this->matchDay($fields, $dt)) {
                $dt = $dt->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }

            // If hour doesn't match, jump to next hour
            $hour = (int)$dt->format('G');
            if (!$this->matchNumericField($fields['hour'], $hour, 0, 23)) {
                $dt = $dt->modify('+1 hour');
                $dt = $dt->setTime((int)$dt->format('G'), 0, 0);
                continue;
            }

            // If minute doesn't match
            $minute = (int)$dt->format('i');
            if (!$this->matchNumericField($fields['minute'], $minute, 0, 59)) {
                $dt = $dt->modify('+1 minute');
                if ($isSecondLevel) {
                    $dt = $dt->setTime((int)$dt->format('G'), (int)$dt->format('i'), 0);
                }
                continue;
            }

            // Advance by step
            $dt = $dt->modify("+{$step} second");
        }

        throw new CronParseException("Unable to find next run date for cron expression: {$expression}");
    }

    /**
     * Get the previous run date matching expression
     */
    public function getPreviousRunDate(string $expression, ?\DateTimeInterface $currentTime = null, string $timezone = 'UTC'): \DateTimeImmutable
    {
        $dt = $this->createDateTime($currentTime, $timezone);
        $fields = $this->parseExpression($expression);

        $isSecondLevel = $fields['is_second_level'];
        $step = $isSecondLevel ? 1 : 60;

        if (!$isSecondLevel) {
            $dt = $dt->setTime((int)$dt->format('H'), (int)$dt->format('i'), 0);
            $dt = $dt->modify('-1 minute');
        } else {
            $dt = $dt->modify('-1 second');
        }

        $maxIterations = 100000;
        $iteration = 0;

        while ($iteration++ < $maxIterations) {
            if ($this->matchesTime($fields, $dt)) {
                return $dt;
            }
            $dt = $dt->modify("-{$step} second");
        }

        throw new CronParseException("Unable to find previous run date for cron expression: {$expression}");
    }

    /**
     * Get the next $count run dates
     */
    public function getNextRunDates(string $expression, int $count = 5, ?\DateTimeInterface $currentTime = null, string $timezone = 'UTC'): array
    {
        $results = [];
        $current = $currentTime;

        for ($i = 0; $i < $count; $i++) {
            $next = $this->getNextRunDate($expression, $current, $timezone);
            $results[] = $next;
            $current = $next;
        }

        return $results;
    }

    private function createDateTime(?\DateTimeInterface $dt, string $timezone): \DateTimeImmutable
    {
        $tz = new \DateTimeZone($timezone);
        if ($dt === null) {
            return new \DateTimeImmutable('now', $tz);
        }

        if ($dt instanceof \DateTimeImmutable) {
            return $dt->setTimezone($tz);
        }

        return \DateTimeImmutable::createFromMutable($dt)->setTimezone($tz);
    }

    public function parseExpression(string $expression): array
    {
        $expression = trim($expression);
        if (isset(self::$macros[$expression])) {
            $expression = self::$macros[$expression];
        }

        $parts = preg_split('/\s+/', $expression);
        $count = count($parts);

        if ($count < 5 || $count > 7) {
            throw new CronParseException("Invalid cron expression '{$expression}'. Expected 5 to 7 fields, got {$count}.");
        }

        $isSecondLevel = false;
        $second = '0';
        $minute = '*';
        $hour = '*';
        $dom = '*';
        $month = '*';
        $dow = '*';
        $year = '*';

        if ($count === 5) {
            // standard 5-part: minute hour dom month dow
            [$minute, $hour, $dom, $month, $dow] = $parts;
        } elseif ($count === 6) {
            // 6-part Quartz: second minute hour dom month dow
            [$second, $minute, $hour, $dom, $month, $dow] = $parts;
            $isSecondLevel = true;
        } elseif ($count === 7) {
            // 7-part Quartz: second minute hour dom month dow year
            [$second, $minute, $hour, $dom, $month, $dow, $year] = $parts;
            $isSecondLevel = true;
        }

        // Normalize month names and day names
        $month = $this->normalizeNames($month, self::$monthMap);
        $dow = $this->normalizeNames($dow, self::$dayOfWeekMap);

        return [
            'raw' => $expression,
            'second' => $second,
            'minute' => $minute,
            'hour' => $hour,
            'dom' => $dom,
            'month' => $month,
            'dow' => $dow,
            'year' => $year,
            'is_second_level' => $isSecondLevel,
        ];
    }

    private function normalizeNames(string $field, array $map): string
    {
        $upper = strtoupper($field);
        foreach ($map as $name => $val) {
            $upper = preg_replace('/\b' . $name . '\b/', (string)$val, $upper);
        }
        return $upper;
    }

    private function matchesTime(array $fields, \DateTimeImmutable $dt): bool
    {
        if ($fields['is_second_level']) {
            $second = (int)$dt->format('s');
            if (!$this->matchNumericField($fields['second'], $second, 0, 59)) {
                return false;
            }
        }

        $minute = (int)$dt->format('i');
        if (!$this->matchNumericField($fields['minute'], $minute, 0, 59)) {
            return false;
        }

        $hour = (int)$dt->format('G');
        if (!$this->matchNumericField($fields['hour'], $hour, 0, 23)) {
            return false;
        }

        $month = (int)$dt->format('n');
        if (!$this->matchNumericField($fields['month'], $month, 1, 12)) {
            return false;
        }

        if ($fields['year'] !== '*') {
            $year = (int)$dt->format('Y');
            if (!$this->matchNumericField($fields['year'], $year, 1970, 2099)) {
                return false;
            }
        }

        return $this->matchDay($fields, $dt);
    }

    private function matchDay(array $fields, \DateTimeImmutable $dt): bool
    {
        $domField = $fields['dom'];
        $dowField = $fields['dow'];

        $domMatch = $this->matchDomField($domField, $dt);
        $dowMatch = $this->matchDowField($dowField, $dt);

        // If both are ?, treat as match
        if ($domField === '?' && $dowField === '?') {
            return true;
        }

        // If one is ?, only the other determines match
        if ($domField === '?' || $domField === '*') {
            return $dowMatch;
        }
        if ($dowField === '?' || $dowField === '*') {
            return $domMatch;
        }

        // In standard cron, if both are specified (and not * or ?), either matching is sufficient
        return $domMatch || $dowMatch;
    }

    private function matchDomField(string $pattern, \DateTimeImmutable $dt): bool
    {
        if ($pattern === '*' || $pattern === '?') {
            return true;
        }

        $dom = (int)$dt->format('j');
        $lastDayOfMonth = (int)$dt->format('t');

        // Quartz 'L' (last day of month)
        if ($pattern === 'L') {
            return $dom === $lastDayOfMonth;
        }

        // Quartz 'L-N' (N days before last day)
        if (preg_match('/^L-(\d+)$/', $pattern, $m)) {
            return $dom === ($lastDayOfMonth - (int)$m[1]);
        }

        // Quartz 'LW' (last weekday of month)
        if ($pattern === 'LW') {
            $lastDay = $dt->setDate((int)$dt->format('Y'), (int)$dt->format('n'), $lastDayOfMonth);
            $dow = (int)$lastDay->format('w');
            $offset = 0;
            if ($dow === 6) { // Saturday
                $offset = 1;
            } elseif ($dow === 0) { // Sunday
                $offset = 2;
            }
            return $dom === ($lastDayOfMonth - $offset);
        }

        // Quartz 'NW' (nearest weekday to day N)
        if (preg_match('/^(\d+)W$/', $pattern, $m)) {
            $targetDay = (int)$m[1];
            $targetDate = $dt->setDate((int)$dt->format('Y'), (int)$dt->format('n'), min($targetDay, $lastDayOfMonth));
            $targetDow = (int)$targetDate->format('w');

            if ($targetDow >= 1 && $targetDow <= 5) {
                return $dom === $targetDay;
            }

            if ($targetDow === 6) { // Saturday -> Friday before (if in same month) or Monday
                $weekday = ($targetDay > 1) ? $targetDay - 1 : $targetDay + 2;
                return $dom === $weekday;
            }

            if ($targetDow === 0) { // Sunday -> Monday after (if in same month) or Friday
                $weekday = ($targetDay < $lastDayOfMonth) ? $targetDay + 1 : $targetDay - 2;
                return $dom === $weekday;
            }
        }

        return $this->matchNumericField($pattern, $dom, 1, 31);
    }

    private function matchDowField(string $pattern, \DateTimeImmutable $dt): bool
    {
        if ($pattern === '*' || $pattern === '?') {
            return true;
        }

        $dow = (int)$dt->format('w'); // 0 (Sun) to 6 (Sat)

        // Quartz 'NL' (last Nth weekday in month, e.g. 5L = last Friday)
        if (preg_match('/^(\d+)L$/', $pattern, $m)) {
            $targetDow = (int)$m[1] % 7;
            if ($dow !== $targetDow) {
                return false;
            }
            // Check if next week is next month
            $nextWeek = $dt->modify('+7 days');
            return (int)$nextWeek->format('n') !== (int)$dt->format('n');
        }

        // Quartz 'N#K' (K-th occurrence of N day-of-week in month, e.g. 6#3)
        if (preg_match('/^(\d+)#(\d+)$/', $pattern, $m)) {
            $targetDow = (int)$m[1] % 7;
            $nth = (int)$m[2];
            if ($dow !== $targetDow) {
                return false;
            }
            $dom = (int)$dt->format('j');
            $currentNth = (int)ceil($dom / 7);
            return $currentNth === $nth;
        }

        return $this->matchNumericField($pattern, $dow, 0, 7);
    }

    private function matchNumericField(string $pattern, int $value, int $min, int $max): bool
    {
        if ($pattern === '*' || $pattern === '?') {
            return true;
        }

        // Handle lists: e.g. "1,2,5"
        if (str_contains($pattern, ',')) {
            foreach (explode(',', $pattern) as $sub) {
                if ($this->matchNumericField($sub, $value, $min, $max)) {
                    return true;
                }
            }
            return false;
        }

        // Handle steps: e.g. "*/5" or "10-30/5"
        if (str_contains($pattern, '/')) {
            [$range, $step] = explode('/', $pattern, 2);
            $step = (int)$step;
            if ($step <= 0) {
                return false;
            }

            $rangeStart = $min;
            $rangeEnd = $max;

            if ($range !== '*' && $range !== '?') {
                if (str_contains($range, '-')) {
                    [$start, $end] = explode('-', $range, 2);
                    $rangeStart = (int)$start;
                    $rangeEnd = (int)$end;
                } else {
                    $rangeStart = (int)$range;
                }
            }

            if ($value < $rangeStart || $value > $rangeEnd) {
                return false;
            }

            return (($value - $rangeStart) % $step) === 0;
        }

        // Handle range: e.g. "1-5"
        if (str_contains($pattern, '-')) {
            [$start, $end] = explode('-', $pattern, 2);
            $start = (int)$start;
            $end = (int)$end;
            return $value >= $start && $value <= $end;
        }

        // Exact match (with 7 mapped to 0 for Sunday if max is 7)
        $intPattern = (int)$pattern;
        if ($max === 7 && $intPattern === 7) {
            $intPattern = 0;
        }

        return $value === $intPattern;
    }
}
