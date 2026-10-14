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

namespace NetFusion\Core\Saga\Store;

use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Saga\Contracts\SagaStoreInterface;
use NetFusion\Core\Saga\SagaContext;
use NetFusion\Core\Saga\SagaInstance;
use NetFusion\Core\Saga\SagaState;
use NetFusion\Core\Saga\SagaStepResult;
use PDO;
use RuntimeException;

class DatabaseSagaStore implements SagaStoreInterface
{
    protected string $instanceTable = 'netfusion_saga_instances';
    protected string $stepsTable = 'netfusion_saga_steps';

    public function __construct(
        protected mixed $connection
    ) {
    }

    protected function getPdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if ($this->connection instanceof DatabaseManager) {
            return $this->connection->connection()->getPdo();
        }

        if (is_object($this->connection)) {
            if (method_exists($this->connection, 'getPdo')) {
                return $this->connection->getPdo();
            }
            if (method_exists($this->connection, 'connection')) {
                $conn = $this->connection->connection();
                if ($conn instanceof PDO) {
                    return $conn;
                }
                if (is_object($conn) && method_exists($conn, 'getPdo')) {
                    return $conn->getPdo();
                }
            }
        }

        throw new RuntimeException("Unable to resolve PDO instance from database connection.");
    }

    public function save(SagaInstance $instance): void
    {
        $pdo = $this->getPdo();
        $now = date('Y-m-d H:i:s');
        $jsonContext = json_encode($instance->context->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 1. Upsert Instance
        $stmt = $pdo->prepare("
            INSERT INTO {$this->instanceTable} (id, saga_name, state, context, current_step_index, error, started_at, completed_at, created_at, updated_at)
            VALUES (:id, :saga_name, :state, :context, :current_step_index, :error, :started_at, :completed_at, :created_at, :updated_at)
            ON CONFLICT(id) DO UPDATE SET
                state = excluded.state,
                context = excluded.context,
                current_step_index = excluded.current_step_index,
                error = excluded.error,
                completed_at = excluded.completed_at,
                updated_at = excluded.updated_at
        ");

        $params = [
            ':id' => $instance->id,
            ':saga_name' => $instance->sagaName,
            ':state' => $instance->state->value,
            ':context' => $jsonContext,
            ':current_step_index' => $instance->currentStepIndex,
            ':error' => $instance->error,
            ':started_at' => $instance->startedAt,
            ':completed_at' => $instance->completedAt,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];

        try {
            $stmt->execute($params);
        } catch (\Throwable) {
            // MySQL fallback
            $stmtFallback = $pdo->prepare("
                INSERT INTO {$this->instanceTable} (id, saga_name, state, context, current_step_index, error, started_at, completed_at, created_at, updated_at)
                VALUES (:id, :saga_name, :state, :context, :current_step_index, :error, :started_at, :completed_at, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    state = VALUES(state),
                    context = VALUES(context),
                    current_step_index = VALUES(current_step_index),
                    error = VALUES(error),
                    completed_at = VALUES(completed_at),
                    updated_at = VALUES(updated_at)
            ");
            $stmtFallback->execute($params);
        }

        // 2. Upsert Step Results
        foreach ($instance->stepResults as $stepResult) {
            $jsonResult = json_encode($stepResult->result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $stepStmt = $pdo->prepare("
                INSERT INTO {$this->stepsTable} (saga_id, step_name, success, result, error, compensated, execution_time_ms, created_at)
                VALUES (:saga_id, :step_name, :success, :result, :error, :compensated, :execution_time_ms, :created_at)
                ON CONFLICT(saga_id, step_name) DO UPDATE SET
                    success = excluded.success,
                    result = excluded.result,
                    error = excluded.error,
                    compensated = excluded.compensated,
                    execution_time_ms = excluded.execution_time_ms
            ");

            $stepParams = [
                ':saga_id' => $instance->id,
                ':step_name' => $stepResult->stepName,
                ':success' => $stepResult->success ? 1 : 0,
                ':result' => $jsonResult,
                ':error' => $stepResult->error,
                ':compensated' => $stepResult->compensated ? 1 : 0,
                ':execution_time_ms' => $stepResult->executionTimeMs,
                ':created_at' => $now,
            ];

            try {
                $stepStmt->execute($stepParams);
            } catch (\Throwable) {
                // MySQL fallback
                $stepStmtFallback = $pdo->prepare("
                    INSERT INTO {$this->stepsTable} (saga_id, step_name, success, result, error, compensated, execution_time_ms, created_at)
                    VALUES (:saga_id, :step_name, :success, :result, :error, :compensated, :execution_time_ms, :created_at)
                    ON DUPLICATE KEY UPDATE
                        success = VALUES(success),
                        result = VALUES(result),
                        error = VALUES(error),
                        compensated = VALUES(compensated),
                        execution_time_ms = VALUES(execution_time_ms)
                ");
                $stepStmtFallback->execute($stepParams);
            }
        }
    }

    public function find(string $id): ?SagaInstance
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM {$this->instanceTable} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Fetch steps
        $stepStmt = $pdo->prepare("SELECT * FROM {$this->stepsTable} WHERE saga_id = :saga_id ORDER BY id ASC");
        $stepStmt->execute([':saga_id' => $id]);
        $stepRows = $stepStmt->fetchAll(PDO::FETCH_ASSOC);

        $stepResults = [];
        foreach ($stepRows as $sRow) {
            $rawRes = $sRow['result'] ?? null;
            $res = $rawRes !== null ? json_decode((string)$rawRes, true) : null;
            $stepResults[$sRow['step_name']] = new SagaStepResult(
                stepName: (string)$sRow['step_name'],
                success: (bool)$sRow['success'],
                result: $res,
                error: $sRow['error'] !== null ? (string)$sRow['error'] : null,
                compensated: (bool)$sRow['compensated'],
                executionTimeMs: (float)($sRow['execution_time_ms'] ?? 0.0)
            );
        }

        $rawContext = $row['context'] ?? '{}';
        $contextData = json_decode((string)$rawContext, true) ?? [];

        return new SagaInstance(
            id: (string)$row['id'],
            sagaName: (string)$row['saga_name'],
            state: SagaState::from((string)$row['state']),
            context: new SagaContext(is_array($contextData) ? $contextData : []),
            currentStepIndex: (int)$row['current_step_index'],
            stepResults: $stepResults,
            error: $row['error'] !== null ? (string)$row['error'] : null,
            startedAt: (float)$row['started_at'],
            completedAt: $row['completed_at'] !== null ? (float)$row['completed_at'] : null
        );
    }

    public function all(array $filters = []): array
    {
        $pdo = $this->getPdo();
        $query = "SELECT id FROM {$this->instanceTable} WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $query .= " AND state = :state";
            $params[':state'] = strtoupper((string)$filters['status']);
        }

        if (!empty($filters['saga_name'])) {
            $query .= " AND saga_name = :saga_name";
            $params[':saga_name'] = (string)$filters['saga_name'];
        }

        $query .= " ORDER BY started_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        foreach ($ids as $id) {
            $instance = $this->find((string)$id);
            if ($instance !== null) {
                $results[] = $instance;
            }
        }

        return $results;
    }

    public function delete(string $id): void
    {
        $pdo = $this->getPdo();
        $stmt1 = $pdo->prepare("DELETE FROM {$this->stepsTable} WHERE saga_id = :id");
        $stmt1->execute([':id' => $id]);

        $stmt2 = $pdo->prepare("DELETE FROM {$this->instanceTable} WHERE id = :id");
        $stmt2->execute([':id' => $id]);
    }
}
