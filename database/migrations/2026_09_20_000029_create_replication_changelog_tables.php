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

use NetFusion\Core\Database\Connection;

return new class {
    /**
     * Run the migrations.
     */
    public function up(Connection|PDO $conn): void
    {
        $exec = function (string $sql) use ($conn) {
            if ($conn instanceof Connection) {
                $conn->statement($sql);
            } else {
                $conn->exec($sql);
            }
        };

        // Replication Changelog Table
        $exec("
            CREATE TABLE IF NOT EXISTS netfusion_replication_changelog (
                id VARCHAR(64) PRIMARY KEY,
                origin_region VARCHAR(32) NOT NULL,
                entity_type VARCHAR(128) NOT NULL,
                entity_id VARCHAR(255) NOT NULL,
                operation VARCHAR(32) NOT NULL,
                payload TEXT NOT NULL,
                vector_clock TEXT NOT NULL,
                timestamp REAL NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL
            )
        ");

        // Indexes for performance
        $exec("CREATE INDEX IF NOT EXISTS idx_repl_status_ts ON netfusion_replication_changelog (status, timestamp)");
        $exec("CREATE INDEX IF NOT EXISTS idx_repl_entity ON netfusion_replication_changelog (entity_type, entity_id)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection|PDO $conn): void
    {
        $exec = function (string $sql) use ($conn) {
            if ($conn instanceof Connection) {
                $conn->statement($sql);
            } else {
                $conn->exec($sql);
            }
        };

        $exec("DROP TABLE IF EXISTS netfusion_replication_changelog");
    }
};
