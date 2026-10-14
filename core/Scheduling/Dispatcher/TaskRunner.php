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

namespace NetFusion\Core\Scheduling\Dispatcher;

use NetFusion\Core\Scheduling\Contracts\TaskInterface;
use NetFusion\Core\Scheduling\Contracts\ScheduleStoreInterface;
use NetFusion\Core\Scheduling\Models\TaskExecution;

class TaskRunner
{
    private ?ScheduleStoreInterface $store;

    public function __construct(?ScheduleStoreInterface $store = null)
    {
        $this->store = $store;
    }

    /**
     * Run a task and persist telemetry
     */
    public function runTask(TaskInterface $task): TaskExecution
    {
        $execution = $task->execute();

        if ($this->store !== null) {
            $this->store->saveExecution($execution);
        }

        return $execution;
    }

    /**
     * Run all due tasks from a list of tasks
     *
     * @param array<TaskInterface> $tasks
     * @param \DateTimeInterface|null $currentTime
     * @return array<TaskExecution>
     */
    public function runDueTasks(array $tasks, ?\DateTimeInterface $currentTime = null): array
    {
        $executions = [];

        foreach ($tasks as $task) {
            if ($task->isDue($currentTime)) {
                $executions[] = $this->runTask($task);
            }
        }

        return $executions;
    }
}
