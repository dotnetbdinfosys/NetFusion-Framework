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

        // 1. Documents Snapshot Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_collab_documents (
            id VARCHAR(128) PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            revision INTEGER NOT NULL DEFAULT 0,
            author_id VARCHAR(128) NULL,
            metadata TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 2. OT Operations Stream Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_collab_operations (
            id VARCHAR(64) PRIMARY KEY,
            document_id VARCHAR(128) NOT NULL,
            revision INTEGER NOT NULL,
            operation TEXT NOT NULL,
            user_id VARCHAR(128) NOT NULL,
            created_at DATETIME NOT NULL
        )");

        // Create indexes
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_collab_ops_doc_rev ON netfusion_collab_operations (document_id, revision)");
        } catch (\Throwable) {
            // Some DBMS create indexes during table creation
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS netfusion_collab_operations");
        $pdo->exec("DROP TABLE IF EXISTS netfusion_collab_documents");
    }
};
