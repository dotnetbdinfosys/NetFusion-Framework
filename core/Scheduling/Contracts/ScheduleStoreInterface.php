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

namespace NetFusion\Core\Scheduling\Contracts;

use NetFusion\Core\Scheduling\Models\TaskExecution;

interface ScheduleStoreInterface
{
    public function registerTask(TaskInterface $task): void;
    public function getTask(string $id): ?TaskInterface;
    public function getAllTasks(): array;
    public function saveExecution(TaskExecution $execution): void;
    public function getExecutions(string $taskId, int $limit = 20): array;

    // Leader Election
    public function acquireLeaderLock(string $nodeId, int $ttlSeconds = 30): bool;
    public function renewLeaderLock(string $nodeId, int $ttlSeconds = 30): bool;
    public function releaseLeaderLock(string $nodeId): bool;
    public function getLeaderNodeId(): ?string;
}
