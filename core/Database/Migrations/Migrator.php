<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Migrations/Migrator.php
| Module    : Core\Database\Migrations
| Namespace : NetFusion\Core\Database\Migrations
| Purpose   : Migration execution engine managing batch tracking, migrations table, and rollback operations.
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

namespace NetFusion\Core\Database\Migrations;

use NetFusion\Core\Database\Connection;
use RuntimeException;

/**
 * NetFusion Database Migrator
 *
 * Scans migration directories, tracks execution state in `netfusion_migrations` table,
 * and coordinates forward migrations and batch rollbacks.
 *
 * @package NetFusion\Core\Database\Migrations
 * @version 1.0.0
 */
class Migrator
{
    /**
     * Migration tracking table name.
     */
    protected string $table = 'netfusion_migrations';

    /**
     * @param Connection $db Active database connection.
     */
    public function __construct(
        protected readonly Connection $db
    ) {
        $this->ensureTableExists();
    }

    /**
     * Ensure tracking table exists in the database.
     */
    protected function ensureTableExists(): void
    {
        $driver = $this->db->getDriverName();

        $sql = match ($driver) {
            'sqlite' => "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    executed_at TEXT NOT NULL
                )
            ",
            'pgsql' => "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ",
            default => "
                CREATE TABLE IF NOT EXISTS `{$this->table}` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `migration` VARCHAR(255) NOT NULL UNIQUE,
                    `batch` INT NOT NULL,
                    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
        };

        $this->db->statement($sql);
    }

    /**
     * Get array of all migration filenames already executed.
     *
     * @return string[]
     */
    public function getRan(): array
    {
        $rows = $this->db->select("SELECT migration FROM {$this->table} ORDER BY id ASC");
        return array_column($rows, 'migration');
    }

    /**
     * Run all pending migrations from given directory paths.
     *
     * @param  string[] $paths
     * @return string[] List of newly migrated file names.
     */
    public function run(array $paths): array
    {
        $files   = $this->getMigrationFiles($paths);
        $ran     = $this->getRan();
        $pending = array_diff(array_keys($files), $ran);

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $migrated = [];

        foreach ($pending as $migrationName) {
            $filePath = $files[$migrationName];

            /** @var Migration $migration */
            $migration = require $filePath;

            if (!($migration instanceof Migration)) {
                throw new RuntimeException("Migration file [{$filePath}] must return an instance of Migration.");
            }

            $this->db->transaction(function (Connection $c) use ($migration, $migrationName, $batch): void {
                $migration->up($c);

                $now = date('Y-m-d H:i:s');
                $c->insert(
                    "INSERT INTO {$this->table} (migration, batch, executed_at) VALUES (?, ?, ?)",
                    [$migrationName, $batch, $now]
                );
            });

            $migrated[] = $migrationName;
        }

        return $migrated;
    }

    /**
     * Rollback the last batch of executed migrations.
     *
     * @param  string[] $paths
     * @return string[] List of rolled-back migration names.
     */
    public function rollback(array $paths): array
    {
        $lastBatch = $this->getLastBatchNumber();
        if ($lastBatch === 0) {
            return [];
        }

        $files = $this->getMigrationFiles($paths);
        $rows  = $this->db->select(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY id DESC",
            [$lastBatch]
        );

        $rolledBack = [];

        foreach ($rows as $row) {
            $migrationName = $row['migration'];

            if (!isset($files[$migrationName])) {
                throw new RuntimeException("Cannot rollback [{$migrationName}]: file not found in paths.");
            }

            /** @var Migration $migration */
            $migration = require $files[$migrationName];

            $this->db->transaction(function (Connection $c) use ($migration, $migrationName): void {
                $migration->down($c);
                $c->delete("DELETE FROM {$this->table} WHERE migration = ?", [$migrationName]);
            });

            $rolledBack[] = $migrationName;
        }

        return $rolledBack;
    }

    /**
     * Get all migration files from given directory paths (name => absolute path).
     *
     * @param  string[] $paths
     * @return array<string, string>
     */
    protected function getMigrationFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $scanned = scandir($path);
            if (!$scanned) {
                continue;
            }

            foreach ($scanned as $file) {
                if (str_ends_with($file, '.php')) {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                    $files[$name] = $path . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        ksort($files);
        return $files;
    }

    /**
     * Get the next batch number.
     */
    protected function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the latest executed batch number.
     */
    protected function getLastBatchNumber(): int
    {
        $row = $this->db->selectOne("SELECT MAX(batch) as max_batch FROM {$this->table}");
        return (int) ($row['max_batch'] ?? 0);
    }
}
