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

namespace NetFusion\Core\Workflow\Contracts\Store;

use NetFusion\Core\Workflow\Definition\WorkflowDefinition;
use NetFusion\Core\Workflow\Execution\ActivityExecution;
use NetFusion\Core\Workflow\Execution\ProcessInstance;

interface WorkflowStateStoreInterface
{
    /**
     * Save or update a workflow definition.
     */
    public function saveDefinition(WorkflowDefinition $definition): void;

    /**
     * Load a workflow definition.
     */
    public function loadDefinition(string $definitionId): ?WorkflowDefinition;

    /**
     * List all workflow definitions.
     *
     * @return array<WorkflowDefinition>
     */
    public function listDefinitions(): array;

    /**
     * Save or update a process instance.
     */
    public function saveInstance(ProcessInstance $instance): void;

    /**
     * Load a process instance.
     */
    public function loadInstance(string $instanceId): ?ProcessInstance;

    /**
     * List process instances by optional criteria.
     *
     * @param string|null $status
     * @param string|null $definitionId
     * @param int $limit
     * @param int $offset
     * @return array<ProcessInstance>
     */
    public function listInstances(?string $status = null, ?string $definitionId = null, int $limit = 50, int $offset = 0): array;

    /**
     * Record an activity execution log entry.
     */
    public function recordActivity(ActivityExecution $activity): void;

    /**
     * Retrieve activity history for a process instance.
     *
     * @return array<ActivityExecution>
     */
    public function getActivityHistory(string $instanceId): array;

    /**
     * Save user task record.
     */
    public function saveUserTask(array $userTaskData): void;

    /**
     * Get pending user tasks by assignee or instance.
     */
    public function getUserTasks(?string $assignee = null, ?string $instanceId = null, ?string $status = 'PENDING'): array;
}
