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
    public function up(Connection $db): void
    {
        // Scheduled tasks registry table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_scheduled_tasks (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                expression VARCHAR(100) NOT NULL,
                timezone VARCHAR(100) DEFAULT 'UTC',
                timeout INTEGER DEFAULT 60,
                misfire_policy VARCHAR(50) DEFAULT 'FIRE_NOW',
                without_overlapping INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Task execution history & telemetry table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_task_executions (
                id VARCHAR(255) PRIMARY KEY,
                task_id VARCHAR(255) NOT NULL,
                task_name VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                output TEXT,
                error TEXT,
                duration REAL DEFAULT 0.0,
                attempts INTEGER DEFAULT 1,
                started_at DATETIME NOT NULL,
                completed_at DATETIME
            )
        ");

        // Cluster leader election lock table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_schedule_cluster_nodes (
                lock_key VARCHAR(100) PRIMARY KEY,
                leader_node_id VARCHAR(255) NOT NULL,
                acquired_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL
            )
        ");

        // DAG pipeline executions table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_schedule_dag_runs (
                id VARCHAR(255) PRIMARY KEY,
                dag_name VARCHAR(255) NOT NULL,
                status VARCHAR(50) NOT NULL,
                duration REAL DEFAULT 0.0,
                result_context TEXT,
                nodes_report TEXT,
                started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS nf_schedule_dag_runs");
        $db->statement("DROP TABLE IF EXISTS nf_schedule_cluster_nodes");
        $db->statement("DROP TABLE IF EXISTS nf_task_executions");
        $db->statement("DROP TABLE IF EXISTS nf_scheduled_tasks");
    }
};
