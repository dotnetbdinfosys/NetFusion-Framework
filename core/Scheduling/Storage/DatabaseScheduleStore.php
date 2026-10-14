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

namespace NetFusion\Core\Scheduling\Storage;

use NetFusion\Core\Scheduling\Contracts\ScheduleStoreInterface;
use NetFusion\Core\Scheduling\Contracts\TaskInterface;
use NetFusion\Core\Scheduling\Models\TaskExecution;
use PDO;

class DatabaseScheduleStore implements ScheduleStoreInterface
{
    private mixed $connection;
    /** @var array<string, TaskInterface> */
    private array $inMemoryTasks = [];
    /** @var array<string, array<TaskExecution>> */
    private array $inMemoryExecutions = [];
    private ?string $inMemoryLeader = null;
    private int $inMemoryLeaderExpires = 0;

    public function __construct(mixed $connection = null)
    {
        $this->connection = $connection;
    }

    private function getPdo(): ?PDO
    {
        if ($this->connection === null) {
            return null;
        }

        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if (is_object($this->connection) && method_exists($this->connection, 'getPdo')) {
            return $this->connection->getPdo();
        }

        return null;
    }

    public function registerTask(TaskInterface $task): void
    {
        $this->inMemoryTasks[$task->getId()] = $task;

        $pdo = $this->getPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    INSERT OR REPLACE INTO nf_scheduled_tasks 
                    (id, name, expression, timezone, timeout, misfire_policy, without_overlapping, created_at, updated_at)
                    VALUES (:id, :name, :expression, :timezone, :timeout, :misfire_policy, :without_overlapping, :created_at, :updated_at)
                ");
                $now = date('Y-m-d H:i:s');
                $stmt->execute([
                    ':id' => $task->getId(),
                    ':name' => $task->getName(),
                    ':expression' => $task->getExpression(),
                    ':timezone' => $task->getTimezone(),
                    ':timeout' => $task->getTimeout(),
                    ':misfire_policy' => $task->getMisfirePolicy(),
                    ':without_overlapping' => $task->isOverlapAllowed() ? 0 : 1,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                // Table might not exist yet; fall back silently to in-memory
            }
        }
    }

    public function getTask(string $id): ?TaskInterface
    {
        return $this->inMemoryTasks[$id] ?? null;
    }

    public function getAllTasks(): array
    {
        return array_values($this->inMemoryTasks);
    }

    public function saveExecution(TaskExecution $execution): void
    {
        $taskId = $execution->getTaskId();
        if (!isset($this->inMemoryExecutions[$taskId])) {
            $this->inMemoryExecutions[$taskId] = [];
        }
        $this->inMemoryExecutions[$taskId][] = $execution;

        $pdo = $this->getPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO nf_task_executions 
                    (id, task_id, task_name, status, output, error, duration, attempts, started_at, completed_at)
                    VALUES (:id, :task_id, :task_name, :status, :output, :error, :duration, :attempts, :started_at, :completed_at)
                ");
                $stmt->execute([
                    ':id' => $execution->getId(),
                    ':task_id' => $execution->getTaskId(),
                    ':task_name' => $execution->getTaskName(),
                    ':status' => $execution->getStatus(),
                    ':output' => $execution->getOutput(),
                    ':error' => $execution->getError(),
                    ':duration' => $execution->getDuration(),
                    ':attempts' => $execution->getAttempts(),
                    ':started_at' => $execution->getStartedAt()->format('Y-m-d H:i:s'),
                    ':completed_at' => $execution->getCompletedAt() ? $execution->getCompletedAt()->format('Y-m-d H:i:s') : null,
                ]);
            } catch (\Throwable $e) {
                // DB error fallback
            }
        }
    }

    public function getExecutions(string $taskId, int $limit = 20): array
    {
        $pdo = $this->getPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM nf_task_executions 
                    WHERE task_id = :task_id 
                    ORDER BY started_at DESC 
                    LIMIT :limit
                ");
                $stmt->bindValue(':task_id', $taskId, PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                // DB error fallback
            }
        }

        $all = $this->inMemoryExecutions[$taskId] ?? [];
        return array_slice(array_reverse($all), 0, $limit);
    }

    public function acquireLeaderLock(string $nodeId, int $ttlSeconds = 30): bool
    {
        $pdo = $this->getPdo();
        $now = time();
        $expiresAt = $now + $ttlSeconds;

        if ($pdo) {
            try {
                // Clean up expired locks first
                $cleanStmt = $pdo->prepare("DELETE FROM nf_schedule_cluster_nodes WHERE expires_at < :now");
                $cleanStmt->execute([':now' => date('Y-m-d H:i:s', $now)]);

                // Try insert or update if already leader
                $stmt = $pdo->prepare("
                    INSERT INTO nf_schedule_cluster_nodes (lock_key, leader_node_id, acquired_at, expires_at)
                    VALUES ('master_scheduler', :node_id, :acquired_at, :expires_at)
                ");
                $stmt->execute([
                    ':node_id' => $nodeId,
                    ':acquired_at' => date('Y-m-d H:i:s', $now),
                    ':expires_at' => date('Y-m-d H:i:s', $expiresAt),
                ]);
                return true;
            } catch (\Throwable $e) {
                // Check if current leader is this node
                return $this->renewLeaderLock($nodeId, $ttlSeconds);
            }
        }

        // In-memory lock fallback
        if ($this->inMemoryLeader === null || $this->inMemoryLeaderExpires < $now || $this->inMemoryLeader === $nodeId) {
            $this->inMemoryLeader = $nodeId;
            $this->inMemoryLeaderExpires = $expiresAt;
            return true;
        }

        return false;
    }

    public function renewLeaderLock(string $nodeId, int $ttlSeconds = 30): bool
    {
        $pdo = $this->getPdo();
        $now = time();
        $expiresAt = $now + $ttlSeconds;

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE nf_schedule_cluster_nodes 
                    SET expires_at = :expires_at 
                    WHERE lock_key = 'master_scheduler' AND leader_node_id = :node_id
                ");
                $stmt->execute([
                    ':expires_at' => date('Y-m-d H:i:s', $expiresAt),
                    ':node_id' => $nodeId,
                ]);
                return $stmt->rowCount() > 0;
            } catch (\Throwable $e) {
                return false;
            }
        }

        if ($this->inMemoryLeader === $nodeId) {
            $this->inMemoryLeaderExpires = $expiresAt;
            return true;
        }

        return false;
    }

    public function releaseLeaderLock(string $nodeId): bool
    {
        $pdo = $this->getPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    DELETE FROM nf_schedule_cluster_nodes 
                    WHERE lock_key = 'master_scheduler' AND leader_node_id = :node_id
                ");
                $stmt->execute([':node_id' => $nodeId]);
                return $stmt->rowCount() > 0;
            } catch (\Throwable $e) {
                return false;
            }
        }

        if ($this->inMemoryLeader === $nodeId) {
            $this->inMemoryLeader = null;
            $this->inMemoryLeaderExpires = 0;
            return true;
        }

        return false;
    }

    public function getLeaderNodeId(): ?string
    {
        $pdo = $this->getPdo();
        $now = time();

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    SELECT leader_node_id FROM nf_schedule_cluster_nodes 
                    WHERE lock_key = 'master_scheduler' AND expires_at > :now
                ");
                $stmt->execute([':now' => date('Y-m-d H:i:s', $now)]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? (string)$row['leader_node_id'] : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        if ($this->inMemoryLeader !== null && $this->inMemoryLeaderExpires > $now) {
            return $this->inMemoryLeader;
        }

        return null;
    }
}
