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

namespace NetFusion\Core\Scheduling\Cluster;

use NetFusion\Core\Scheduling\Contracts\TaskInterface;
use NetFusion\Core\Scheduling\Models\MisfirePolicy;
use NetFusion\Core\Scheduling\Models\TaskExecution;
use NetFusion\Core\Scheduling\Cron\QuartzExpression;

class MisfireHandler
{
    private QuartzExpression $parser;

    public function __construct(?QuartzExpression $parser = null)
    {
        $this->parser = $parser ?? new QuartzExpression();
    }

    /**
     * Check if a task had a misfire between lastRun and currentTime
     */
    public function detectMisfire(TaskInterface $task, ?\DateTimeInterface $lastRun = null, ?\DateTimeInterface $currentTime = null): bool
    {
        if ($lastRun === null) {
            return false;
        }

        $now = $currentTime ?? new \DateTimeImmutable('now', new \DateTimeZone($task->getTimezone()));
        $nextScheduled = $this->parser->getNextRunDate($task->getExpression(), $lastRun, $task->getTimezone());

        // If nextScheduled is in the past compared to $now, a misfire occurred
        return $nextScheduled->getTimestamp() < $now->getTimestamp();
    }

    /**
     * Handle misfire according to task policy
     *
     * @return TaskExecution|null Execution result if fired, or null if ignored/rescheduled
     */
    public function handleMisfire(TaskInterface $task, ?\DateTimeInterface $lastRun = null, ?\DateTimeInterface $currentTime = null): ?TaskExecution
    {
        $policy = $task->getMisfirePolicy();

        switch ($policy) {
            case MisfirePolicy::FIRE_NOW:
                return $task->execute();

            case MisfirePolicy::IGNORE:
            case MisfirePolicy::RESCHEDULE_NEXT:
            default:
                return null;
        }
    }
}
