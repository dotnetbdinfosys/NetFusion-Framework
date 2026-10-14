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

        // 1. Vector Collections Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_vector_collections (
                name VARCHAR(128) PRIMARY KEY,
                dimension INT NOT NULL,
                metric VARCHAR(32) NOT NULL,
                metadata TEXT NULL,
                created_at INT NOT NULL,
                updated_at INT NOT NULL
            )
        ");

        // 2. Vector Documents Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_vector_documents (
                id VARCHAR(128) NOT NULL,
                collection_name VARCHAR(128) NOT NULL,
                parent_id VARCHAR(128) NULL,
                chunk_index INT NULL,
                content TEXT NOT NULL,
                embedding TEXT NULL,
                metadata TEXT NULL,
                created_at INT NOT NULL,
                PRIMARY KEY (id, collection_name)
            )
        ");

        // Index on collection_name for fast lookups
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_nf_vector_docs_col ON nf_vector_documents(collection_name)
        ");

        // 3. Vector Graphs Index Cache Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nf_vector_graphs (
                collection_name VARCHAR(128) PRIMARY KEY,
                hnsw_data TEXT NULL,
                bm25_data TEXT NULL,
                updated_at INT NOT NULL
            )
        ");
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS nf_vector_graphs");
        $pdo->exec("DROP TABLE IF EXISTS nf_vector_documents");
        $pdo->exec("DROP TABLE IF EXISTS nf_vector_collections");
    }
};
