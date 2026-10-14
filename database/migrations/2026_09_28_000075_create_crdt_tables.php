<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - CRDT Tables Migration
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

use NetFusion\Core\Database\Connection;

return new class {
    /**
     * Run the migrations.
     */
    public function up(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS crdt_documents (
                id VARCHAR(191) PRIMARY KEY,
                type VARCHAR(50) NOT NULL,
                state_json TEXT NOT NULL,
                vector_clock TEXT NOT NULL,
                version INTEGER DEFAULT 0,
                updated_at DATETIME NOT NULL
            );");

            $pdo->exec("CREATE TABLE IF NOT EXISTS crdt_operations (
                id VARCHAR(191) PRIMARY KEY,
                document_id VARCHAR(191) NOT NULL,
                node_id VARCHAR(100) NOT NULL,
                sequence INTEGER NOT NULL,
                operation_type VARCHAR(50) NOT NULL,
                delta_payload TEXT NOT NULL,
                created_at DATETIME NOT NULL
            );");

            $pdo->exec("CREATE TABLE IF NOT EXISTS crdt_peers (
                node_id VARCHAR(100) PRIMARY KEY,
                vector_clock TEXT NOT NULL,
                last_seen_at DATETIME NOT NULL
            );");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS crdt_documents (
                id VARCHAR(191) PRIMARY KEY,
                type VARCHAR(50) NOT NULL,
                state_json LONGTEXT NOT NULL,
                vector_clock TEXT NOT NULL,
                version BIGINT DEFAULT 0,
                updated_at DATETIME NOT NULL,
                INDEX idx_crdt_doc_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS crdt_operations (
                id VARCHAR(191) PRIMARY KEY,
                document_id VARCHAR(191) NOT NULL,
                node_id VARCHAR(100) NOT NULL,
                sequence BIGINT NOT NULL,
                operation_type VARCHAR(50) NOT NULL,
                delta_payload LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_crdt_op_doc (document_id, sequence)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS crdt_peers (
                node_id VARCHAR(100) PRIMARY KEY,
                vector_clock TEXT NOT NULL,
                last_seen_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS crdt_operations;");
        $pdo->exec("DROP TABLE IF EXISTS crdt_documents;");
        $pdo->exec("DROP TABLE IF EXISTS crdt_peers;");
    }
};
