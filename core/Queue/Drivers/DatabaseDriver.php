<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/Drivers/DatabaseDriver.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue\Drivers
| Purpose   : Database-backed persistent queue driver with transactional locking.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Queue\Drivers;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Queue\Contracts\JobInterface;
use NetFusion\Core\Queue\Contracts\QueueDriverInterface;
use Throwable;

/**
 * NetFusion Database Queue Driver
 *
 * Persists queued jobs into the `netfusion_jobs` database table.
 * Uses `reserved_at` timestamp lock to allow multiple parallel workers
 * to safely poll without race conditions.
 *
 * @package NetFusion\Core\Queue\Drivers
 * @version 1.0.0
 */
class DatabaseDriver implements QueueDriverInterface
{
    /**
     * @param Connection $connection Database connection.
     * @param string     $table      Queue table name (default: netfusion_jobs).
     */
    public function __construct(
        protected readonly Connection $connection,
        protected readonly string     $table = 'netfusion_jobs',
    ) {}

    /**
     * {@inheritDoc}
     */
    public function push(JobInterface|string $job, string $queue = 'default'): string
    {
        return $this->pushToDatabase(0, $job, $queue);
    }

    /**
     * {@inheritDoc}
     */
    public function later(int $delay, JobInterface|string $job, string $queue = 'default'): string
    {
        return $this->pushToDatabase($delay, $job, $queue);
    }

    /**
     * Push job record to database.
     */
    protected function pushToDatabase(int $delay, JobInterface|string $job, string $queue): string
    {
        $id = bin2hex(random_bytes(16));
        $now = time();
        $availableAt = $now + max(0, $delay);

        if ($job instanceof JobInterface) {
            $job->setJobId($id);
            $job->onQueue($queue);
            $payload = serialize($job);
        } else {
            $payload = $job;
        }

        $sql = "INSERT INTO {$this->table} (id, queue, payload, attempts, reserved_at, available_at, created_at)
                VALUES (?, ?, ?, 0, NULL, ?, ?)";

        $this->connection->insert($sql, [
            $id,
            $queue,
            $payload,
            $availableAt,
            $now,
        ]);

        return $id;
    }

    /**
     * {@inheritDoc}
     */
    public function pop(string $queue = 'default'): ?JobInterface
    {
        $now = time();
        $lockTimeout = 120; // 2 minutes lock expiration
        $expiredLockTime = $now - $lockTimeout;

        // Atomic selection: find next available job (unreserved or expired reservation)
        $sql = "SELECT * FROM {$this->table}
                WHERE queue = ?
                  AND available_at <= ?
                  AND (reserved_at IS NULL OR reserved_at <= ?)
                ORDER BY id ASC
                LIMIT 1";

        $rows = $this->connection->select($sql, [$queue, $now, $expiredLockTime]);

        if (empty($rows)) {
            return null;
        }

        $record = (array) $rows[0];
        $jobId = $record['id'];

        // Reserve the job atomically
        $updated = $this->connection->update(
            "UPDATE {$this->table} SET reserved_at = ?, attempts = attempts + 1 WHERE id = ? AND (reserved_at IS NULL OR reserved_at <= ?)",
            [$now, $jobId, $expiredLockTime]
        );

        if ($updated === 0) {
            // Another worker grabbed it concurrently, try again next cycle
            return null;
        }

        /** @var JobInterface $job */
        $job = unserialize($record['payload']);
        $job->setJobId((string)$jobId);
        $job->onQueue($queue);

        // Synchronize attempt count
        while ($job->attempts() < (int)$record['attempts'] + 1) {
            $job->incrementAttempts();
        }

        return $job;
    }

    /**
     * {@inheritDoc}
     */
    public function acknowledge(JobInterface $job): void
    {
        $id = $job->getJobId();
        if ($id !== null) {
            $this->connection->delete("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function release(JobInterface $job, int $delay = 0): void
    {
        $id = $job->getJobId();
        if ($id !== null) {
            $availableAt = time() + max(0, $delay);
            $this->connection->update(
                "UPDATE {$this->table} SET reserved_at = NULL, available_at = ?, payload = ? WHERE id = ?",
                [$availableAt, serialize($job), $id]
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $queue = 'default'): int
    {
        $rows = $this->connection->select("SELECT COUNT(*) as count FROM {$this->table} WHERE queue = ?", [$queue]);
        return (int) (($rows[0]['count'] ?? ($rows[0]->count ?? 0)));
    }

    /**
     * {@inheritDoc}
     */
    public function purge(string $queue = 'default'): int
    {
        return $this->connection->delete("DELETE FROM {$this->table} WHERE queue = ?", [$queue]);
    }
}
