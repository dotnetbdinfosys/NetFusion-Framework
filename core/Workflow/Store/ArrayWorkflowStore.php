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

namespace NetFusion\Core\Workflow\Store;

use NetFusion\Core\Workflow\Contracts\Store\WorkflowStateStoreInterface;
use NetFusion\Core\Workflow\Definition\WorkflowDefinition;
use NetFusion\Core\Workflow\Execution\ActivityExecution;
use NetFusion\Core\Workflow\Execution\ProcessInstance;

class ArrayWorkflowStore implements WorkflowStateStoreInterface
{
    /** @var array<string, WorkflowDefinition> */
    protected array $definitions = [];

    /** @var array<string, ProcessInstance> */
    protected array $instances = [];

    /** @var array<string, array<ActivityExecution>> */
    protected array $activityLogs = [];

    /** @var array<string, array<string, mixed>> */
    protected array $userTasks = [];

    public function saveDefinition(WorkflowDefinition $definition): void
    {
        $this->definitions[$definition->getId()] = $definition;
    }

    public function loadDefinition(string $definitionId): ?WorkflowDefinition
    {
        return $this->definitions[$definitionId] ?? null;
    }

    public function listDefinitions(): array
    {
        return array_values($this->definitions);
    }

    public function saveInstance(ProcessInstance $instance): void
    {
        $this->instances[$instance->getId()] = $instance;
    }

    public function loadInstance(string $instanceId): ?ProcessInstance
    {
        return $this->instances[$instanceId] ?? null;
    }

    public function listInstances(?string $status = null, ?string $definitionId = null, int $limit = 50, int $offset = 0): array
    {
        $filtered = array_values($this->instances);

        if ($status !== null) {
            $filtered = array_values(array_filter($filtered, fn (ProcessInstance $i) => $i->getStatus() === $status));
        }

        if ($definitionId !== null) {
            $filtered = array_values(array_filter($filtered, fn (ProcessInstance $i) => $i->getDefinitionId() === $definitionId));
        }

        return array_slice($filtered, $offset, $limit);
    }

    public function recordActivity(ActivityExecution $activity): void
    {
        $this->activityLogs[$activity->getInstanceId()][] = $activity;
    }

    public function getActivityHistory(string $instanceId): array
    {
        return $this->activityLogs[$instanceId] ?? [];
    }

    public function saveUserTask(array $userTaskData): void
    {
        $id = $userTaskData['id'] ?? uniqid('utask_', true);
        $this->userTasks[$id] = $userTaskData;
    }

    public function getUserTasks(?string $assignee = null, ?string $instanceId = null, ?string $status = 'PENDING'): array
    {
        $tasks = array_values($this->userTasks);

        if ($assignee !== null) {
            $tasks = array_values(array_filter($tasks, fn ($t) => ($t['assignee'] ?? null) === $assignee));
        }

        if ($instanceId !== null) {
            $tasks = array_values(array_filter($tasks, fn ($t) => ($t['instanceId'] ?? null) === $instanceId));
        }

        if ($status !== null) {
            $tasks = array_values(array_filter($tasks, fn ($t) => ($t['status'] ?? null) === $status));
        }

        return $tasks;
    }
}
