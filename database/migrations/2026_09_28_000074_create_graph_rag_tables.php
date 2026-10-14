<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - GraphRAG Tables Migration
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
    public function up(Connection $db): void
    {
        // 1. Multi-Modal Knowledge Graph Nodes & Embeddings
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS graphrag_nodes (
                id VARCHAR(64) PRIMARY KEY,
                label VARCHAR(64) NOT NULL DEFAULT 'Entity',
                modality VARCHAR(32) NOT NULL DEFAULT 'text',
                content TEXT NOT NULL,
                vector_embedding TEXT NULL,
                properties TEXT NULL,
                confidence REAL NOT NULL DEFAULT 1.0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 2. Relational Semantic Edges
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS graphrag_edges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_id VARCHAR(64) NOT NULL,
                target_id VARCHAR(64) NOT NULL,
                relationship VARCHAR(64) NOT NULL DEFAULT 'RELATED_TO',
                weight REAL NOT NULL DEFAULT 1.0,
                properties TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 3. Document Manifest Registry
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS graphrag_documents (
                id VARCHAR(64) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                modality VARCHAR(32) NOT NULL DEFAULT 'text',
                chunk_count INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("DROP TABLE IF EXISTS graphrag_documents;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS graphrag_edges;");
        $db->getPdo()->exec("DROP TABLE IF EXISTS graphrag_nodes;");
    }
};
