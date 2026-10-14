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

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Workflow\Contracts\Store\WorkflowStateStoreInterface;
use NetFusion\Core\Workflow\Definition\WorkflowDefinition;
use NetFusion\Core\Workflow\Execution\ActivityExecution;
use NetFusion\Core\Workflow\Execution\ProcessInstance;
use PDO;

class DatabaseWorkflowStore implements WorkflowStateStoreInterface
{
    protected PDO $pdo;

    public function __construct(PDO|Connection $connection)
    {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
    }

    public function saveDefinition(WorkflowDefinition $definition): void
    {
        $sql = "
            INSERT INTO nf_workflow_definitions (
                id, name, version, description, definition_json, is_active, created_at, updated_at
            ) VALUES (
                :id, :name, :version, :description, :definition_json, :is_active, :created_at, :updated_at
            )
        ";

        // Try insert or replace
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $definition->getId(),
                ':name' => $definition->getName(),
                ':version' => $definition->getVersion(),
                ':description' => $definition->getDescription(),
                ':definition_json' => $definition->toJson(),
                ':is_active' => $definition->isExecutable() ? 1 : 0,
                ':created_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            $updateSql = "
                UPDATE nf_workflow_definitions SET
                    name = :name,
                    version = :version,
                    description = :description,
                    definition_json = :definition_json,
                    is_active = :is_active,
                    updated_at = :updated_at
                WHERE id = :id
            ";
            $stmt = $this->pdo->prepare($updateSql);
            $stmt->execute([
                ':id' => $definition->getId(),
                ':name' => $definition->getName(),
                ':version' => $definition->getVersion(),
                ':description' => $definition->getDescription(),
                ':definition_json' => $definition->toJson(),
                ':is_active' => $definition->isExecutable() ? 1 : 0,
                ':updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function loadDefinition(string $definitionId): ?WorkflowDefinition
    {
        $stmt = $this->pdo->prepare("SELECT definition_json FROM nf_workflow_definitions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $definitionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['definition_json'])) {
            return null;
        }

        return WorkflowDefinition::fromJson($row['definition_json']);
    }

    public function listDefinitions(): array
    {
        $stmt = $this->pdo->query("SELECT definition_json FROM nf_workflow_definitions ORDER BY id ASC");
        $definitions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['definition_json'])) {
                $definitions[] = WorkflowDefinition::fromJson($row['definition_json']);
            }
        }
        return $definitions;
    }

    public function saveInstance(ProcessInstance $instance): void
    {
        $data = $instance->toArray();
        $variablesJson = json_encode($data['variables']);
        $tokensJson = json_encode($data['tokens']);
        $compJson = json_encode($data['compensationStack']);
        $histJson = json_encode($data['history']);

        $sql = "
            INSERT INTO nf_workflow_instances (
                id, definition_id, business_key, status, variables_json, tokens_json,
                compensation_json, history_json, created_at, updated_at, completed_at
            ) VALUES (
                :id, :definition_id, :business_key, :status, :variables_json, :tokens_json,
                :compensation_json, :history_json, :created_at, :updated_at, :completed_at
            )
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $instance->getId(),
                ':definition_id' => $instance->getDefinitionId(),
                ':business_key' => $instance->getBusinessKey(),
                ':status' => $instance->getStatus(),
                ':variables_json' => $variablesJson,
                ':tokens_json' => $tokensJson,
                ':compensation_json' => $compJson,
                ':history_json' => $histJson,
                ':created_at' => $instance->getCreatedAt(),
                ':updated_at' => $instance->getUpdatedAt(),
                ':completed_at' => $instance->getCompletedAt(),
            ]);
        } catch (\Throwable) {
            $updateSql = "
                UPDATE nf_workflow_instances SET
                    status = :status,
                    variables_json = :variables_json,
                    tokens_json = :tokens_json,
                    compensation_json = :compensation_json,
                    history_json = :history_json,
                    updated_at = :updated_at,
                    completed_at = :completed_at
                WHERE id = :id
            ";
            $stmt = $this->pdo->prepare($updateSql);
            $stmt->execute([
                ':id' => $instance->getId(),
                ':status' => $instance->getStatus(),
                ':variables_json' => $variablesJson,
                ':tokens_json' => $tokensJson,
                ':compensation_json' => $compJson,
                ':history_json' => $histJson,
                ':updated_at' => $instance->getUpdatedAt(),
                ':completed_at' => $instance->getCompletedAt(),
            ]);
        }
    }

    public function loadInstance(string $instanceId): ?ProcessInstance
    {
        $stmt = $this->pdo->prepare("SELECT * FROM nf_workflow_instances WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $instanceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $data = [
            'id' => $row['id'],
            'definitionId' => $row['definition_id'],
            'businessKey' => $row['business_key'] ?? null,
            'status' => $row['status'],
            'variables' => json_decode($row['variables_json'] ?? '{}', true) ?: [],
            'tokens' => json_decode($row['tokens_json'] ?? '{}', true) ?: [],
            'compensationStack' => json_decode($row['compensation_json'] ?? '[]', true) ?: [],
            'history' => json_decode($row['history_json'] ?? '[]', true) ?: [],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
            'completedAt' => $row['completed_at'] ?? null,
        ];

        return ProcessInstance::fromArray($data);
    }

    public function listInstances(?string $status = null, ?string $definitionId = null, int $limit = 50, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = "status = :status";
            $params[':status'] = $status;
        }

        if ($definitionId !== null) {
            $where[] = "definition_id = :definition_id";
            $params[':definition_id'] = $definitionId;
        }

        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT * FROM nf_workflow_instances {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $instances = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = [
                'id' => $row['id'],
                'definitionId' => $row['definition_id'],
                'businessKey' => $row['business_key'] ?? null,
                'status' => $row['status'],
                'variables' => json_decode($row['variables_json'] ?? '{}', true) ?: [],
                'tokens' => json_decode($row['tokens_json'] ?? '{}', true) ?: [],
                'compensationStack' => json_decode($row['compensation_json'] ?? '[]', true) ?: [],
                'history' => json_decode($row['history_json'] ?? '[]', true) ?: [],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
                'completedAt' => $row['completed_at'] ?? null,
            ];
            $instances[] = ProcessInstance::fromArray($data);
        }

        return $instances;
    }

    public function recordActivity(ActivityExecution $activity): void
    {
        $sql = "
            INSERT INTO nf_workflow_activity_log (
                id, instance_id, node_id, node_type, action, input_json, output_json,
                status, duration_ms, error_message, created_at
            ) VALUES (
                :id, :instance_id, :node_id, :node_type, :action, :input_json, :output_json,
                :status, :duration_ms, :error_message, :created_at
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $activity->getId(),
            ':instance_id' => $activity->getInstanceId(),
            ':node_id' => $activity->getNodeId(),
            ':node_type' => $activity->getNodeType(),
            ':action' => $activity->getAction(),
            ':input_json' => json_encode($activity->getInput()),
            ':output_json' => json_encode($activity->getOutput()),
            ':status' => $activity->getStatus(),
            ':duration_ms' => $activity->getDurationMs(),
            ':error_message' => $activity->getErrorMessage(),
            ':created_at' => $activity->getCreatedAt(),
        ]);
    }

    public function getActivityHistory(string $instanceId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM nf_workflow_activity_log WHERE instance_id = :instance_id ORDER BY id ASC");
        $stmt->execute([':instance_id' => $instanceId]);

        $history = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $history[] = new ActivityExecution(
                $row['id'],
                $row['instance_id'],
                $row['node_id'],
                $row['node_type'],
                $row['action'],
                json_decode($row['input_json'] ?? '{}', true) ?: [],
                json_decode($row['output_json'] ?? '{}', true) ?: [],
                $row['status'],
                (float) ($row['duration_ms'] ?? 0.0),
                $row['error_message'] ?? null,
                $row['created_at']
            );
        }

        return $history;
    }

    public function saveUserTask(array $userTaskData): void
    {
        $sql = "
            INSERT INTO nf_workflow_user_tasks (
                id, instance_id, node_id, assignee, status, form_data_json, created_at, resolved_at
            ) VALUES (
                :id, :instance_id, :node_id, :assignee, :status, :form_data_json, :created_at, :resolved_at
            )
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $userTaskData['id'] ?? uniqid('utask_', true),
                ':instance_id' => $userTaskData['instanceId'],
                ':node_id' => $userTaskData['nodeId'],
                ':assignee' => $userTaskData['assignee'] ?? null,
                ':status' => $userTaskData['status'] ?? 'PENDING',
                ':form_data_json' => json_encode($userTaskData['formData'] ?? []),
                ':created_at' => $userTaskData['createdAt'] ?? date('Y-m-d H:i:s'),
                ':resolved_at' => $userTaskData['resolvedAt'] ?? null,
            ]);
        } catch (\Throwable) {
            $updateSql = "
                UPDATE nf_workflow_user_tasks SET
                    status = :status,
                    form_data_json = :form_data_json,
                    resolved_at = :resolved_at
                WHERE id = :id
            ";
            $stmt = $this->pdo->prepare($updateSql);
            $stmt->execute([
                ':id' => $userTaskData['id'],
                ':status' => $userTaskData['status'] ?? 'PENDING',
                ':form_data_json' => json_encode($userTaskData['formData'] ?? []),
                ':resolved_at' => $userTaskData['resolvedAt'] ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function getUserTasks(?string $assignee = null, ?string $instanceId = null, ?string $status = 'PENDING'): array
    {
        $where = [];
        $params = [];

        if ($assignee !== null) {
            $where[] = "assignee = :assignee";
            $params[':assignee'] = $assignee;
        }

        if ($instanceId !== null) {
            $where[] = "instance_id = :instance_id";
            $params[':instance_id'] = $instanceId;
        }

        if ($status !== null) {
            $where[] = "status = :status";
            $params[':status'] = $status;
        }

        $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT * FROM nf_workflow_user_tasks {$whereClause} ORDER BY created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = [
                'id' => $row['id'],
                'instanceId' => $row['instance_id'],
                'nodeId' => $row['node_id'],
                'assignee' => $row['assignee'] ?? null,
                'status' => $row['status'],
                'formData' => json_decode($row['form_data_json'] ?? '{}', true) ?: [],
                'createdAt' => $row['created_at'],
                'resolvedAt' => $row['resolved_at'] ?? null,
            ];
        }

        return $tasks;
    }
}
