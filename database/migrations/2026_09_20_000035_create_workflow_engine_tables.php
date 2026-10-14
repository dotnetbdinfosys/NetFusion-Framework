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

        // 1. Workflow Definitions Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_workflow_definitions (
            id VARCHAR(128) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            version INTEGER NOT NULL DEFAULT 1,
            description TEXT,
            definition_json TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at VARCHAR(32) NOT NULL,
            updated_at VARCHAR(32) NOT NULL
        )");

        // 2. Workflow Instances Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_workflow_instances (
            id VARCHAR(128) PRIMARY KEY,
            definition_id VARCHAR(128) NOT NULL,
            business_key VARCHAR(128),
            status VARCHAR(32) NOT NULL,
            variables_json TEXT NOT NULL,
            tokens_json TEXT NOT NULL,
            compensation_json TEXT,
            history_json TEXT,
            created_at VARCHAR(32) NOT NULL,
            updated_at VARCHAR(32) NOT NULL,
            completed_at VARCHAR(32)
        )");

        // 3. Workflow Activity Log Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_workflow_activity_log (
            id VARCHAR(64) PRIMARY KEY,
            instance_id VARCHAR(128) NOT NULL,
            node_id VARCHAR(128) NOT NULL,
            node_type VARCHAR(64) NOT NULL,
            action VARCHAR(64) NOT NULL,
            input_json TEXT,
            output_json TEXT,
            status VARCHAR(32) NOT NULL,
            duration_ms DOUBLE NOT NULL DEFAULT 0.0,
            error_message TEXT,
            created_at VARCHAR(32) NOT NULL
        )");

        // 4. Workflow User Tasks Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_workflow_user_tasks (
            id VARCHAR(128) PRIMARY KEY,
            instance_id VARCHAR(128) NOT NULL,
            node_id VARCHAR(128) NOT NULL,
            assignee VARCHAR(128),
            status VARCHAR(32) NOT NULL,
            form_data_json TEXT,
            created_at VARCHAR(32) NOT NULL,
            resolved_at VARCHAR(32)
        )");

        // Indices
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wf_instance_status ON nf_workflow_instances (status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wf_instance_def ON nf_workflow_instances (definition_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wf_activity_instance ON nf_workflow_activity_log (instance_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wf_utasks_assignee ON nf_workflow_user_tasks (assignee, status)");
        } catch (\Throwable) {
            // Indices optional for sqlite fallback
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $pdo->exec("DROP TABLE IF EXISTS nf_workflow_user_tasks");
        $pdo->exec("DROP TABLE IF EXISTS nf_workflow_activity_log");
        $pdo->exec("DROP TABLE IF EXISTS nf_workflow_instances");
        $pdo->exec("DROP TABLE IF EXISTS nf_workflow_definitions");
    }
};
