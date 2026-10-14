<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Queue/FailedJobProvider.php
| Module    : Core\Queue
| Namespace : NetFusion\Core\Queue
| Purpose   : Manages logging, listing, and retrying permanently failed queue jobs.
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

namespace NetFusion\Core\Queue;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Queue\Contracts\JobInterface;
use Throwable;

/**
 * NetFusion Failed Job Provider
 *
 * Records jobs that exceeded their retry limit into `netfusion_failed_jobs`
 * and facilitates inspection and manual replay.
 *
 * @package NetFusion\Core\Queue
 * @version 1.0.0
 */
class FailedJobProvider
{
    /**
     * @param Connection $connection
     * @param string     $table
     */
    public function __construct(
        protected readonly Connection $connection,
        protected readonly string     $table = 'netfusion_failed_jobs',
    ) {}

    /**
     * Record a failed job in storage.
     *
     * @param  JobInterface $job
     * @param  Throwable    $exception
     * @return string                  Failed record ID.
     */
    public function log(JobInterface $job, Throwable $exception): string
    {
        $id = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO {$this->table} (id, connection, queue, payload, exception, failed_at)
                VALUES (?, ?, ?, ?, ?, ?)";

        $this->connection->insert($sql, [
            $id,
            config('queue.default', 'database'),
            $job->getQueue(),
            serialize($job),
            (string) $exception,
            $now,
        ]);

        return $id;
    }

    /**
     * Get all failed jobs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $rows = $this->connection->select("SELECT * FROM {$this->table} ORDER BY failed_at DESC");
        return array_map(fn($r) => (array) $r, $rows);
    }

    /**
     * Find a specific failed job record by ID.
     *
     * @param  string $id
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $rows = $this->connection->select("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1", [$id]);
        return !empty($rows) ? (array) $rows[0] : null;
    }

    /**
     * Delete a failed job by ID.
     *
     * @param  string $id
     * @return bool
     */
    public function forget(string $id): bool
    {
        return $this->connection->delete("DELETE FROM {$this->table} WHERE id = ?", [$id]) > 0;
    }

    /**
     * Flush all failed jobs.
     *
     * @return int Number of deleted records.
     */
    public function flush(): int
    {
        return $this->connection->delete("DELETE FROM {$this->table}");
    }
}
