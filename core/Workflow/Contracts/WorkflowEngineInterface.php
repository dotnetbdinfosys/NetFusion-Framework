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

namespace NetFusion\Core\Workflow\Contracts;

use NetFusion\Core\Workflow\Definition\WorkflowDefinition;
use NetFusion\Core\Workflow\Execution\ProcessInstance;

interface WorkflowEngineInterface
{
    /**
     * Register a workflow definition.
     */
    public function register(WorkflowDefinition $definition): self;

    /**
     * Start a new workflow instance.
     *
     * @param string $definitionId
     * @param array<string, mixed> $variables
     * @param string|null $businessKey
     * @return ProcessInstance
     */
    public function start(string $definitionId, array $variables = [], ?string $businessKey = null): ProcessInstance;

    /**
     * Resume or send a signal to a suspended workflow instance.
     *
     * @param string $instanceId
     * @param string $activityId
     * @param array<string, mixed> $payload
     * @return ProcessInstance
     */
    public function signal(string $instanceId, string $activityId, array $payload = []): ProcessInstance;

    /**
     * Complete a pending user task.
     *
     * @param string $instanceId
     * @param string $taskId
     * @param array<string, mixed> $data
     * @param string|null $completedBy
     * @return ProcessInstance
     */
    public function completeUserTask(string $instanceId, string $taskId, array $data = [], ?string $completedBy = null): ProcessInstance;

    /**
     * Retrieve a workflow instance by ID.
     */
    public function getInstance(string $instanceId): ?ProcessInstance;

    /**
     * Retrieve a workflow definition by ID.
     */
    public function getDefinition(string $definitionId): ?WorkflowDefinition;

    /**
     * Cancel a running or suspended workflow instance.
     */
    public function cancel(string $instanceId, string $reason = 'Cancelled by user'): ProcessInstance;
}
