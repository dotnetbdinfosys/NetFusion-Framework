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
    public function up(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        // 1. Actor State Snapshots Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_actor_snapshots (
            persistence_id VARCHAR(128) PRIMARY KEY,
            sequence_number INTEGER NOT NULL,
            state_data TEXT NOT NULL,
            updated_at DOUBLE NOT NULL
        )");

        // 2. Actor Event Journal Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_actor_events (
            id VARCHAR(64) PRIMARY KEY,
            persistence_id VARCHAR(128) NOT NULL,
            sequence_number INTEGER NOT NULL,
            event_data TEXT NOT NULL,
            created_at DOUBLE NOT NULL
        )");

        // Index for event journal replay
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_actor_journal ON netfusion_actor_events (persistence_id, sequence_number)");
        } catch (\Throwable) {
            // Fallback
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS netfusion_actor_events");
        $pdo->exec("DROP TABLE IF EXISTS netfusion_actor_snapshots");
    }
};
