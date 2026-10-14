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

        // 1. IMDG Distributed Map Entries Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_imdg_map_entries (
            map_name VARCHAR(128) NOT NULL,
            entry_key VARCHAR(255) NOT NULL,
            entry_value TEXT NOT NULL,
            expires_at DOUBLE NOT NULL DEFAULT 0.0,
            updated_at DOUBLE NOT NULL,
            PRIMARY KEY (map_name, entry_key)
        )");

        // 2. IMDG Cluster Topology & Node Discovery Registry
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_imdg_cluster_nodes (
            node_id VARCHAR(128) PRIMARY KEY,
            cluster_name VARCHAR(128) NOT NULL,
            host VARCHAR(128) NOT NULL,
            port INTEGER NOT NULL DEFAULT 5701,
            weight INTEGER NOT NULL DEFAULT 1,
            status VARCHAR(32) NOT NULL DEFAULT 'ALIVE',
            last_heartbeat DOUBLE NOT NULL,
            attributes_json TEXT
        )");

        // Indices
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_imdg_expires ON nf_imdg_map_entries (expires_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_imdg_cluster ON nf_imdg_cluster_nodes (cluster_name, status)");
        } catch (\Throwable) {
            // Optional SQLite index fallback
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $pdo->exec("DROP TABLE IF EXISTS nf_imdg_map_entries");
        $pdo->exec("DROP TABLE IF EXISTS nf_imdg_cluster_nodes");
    }
};
