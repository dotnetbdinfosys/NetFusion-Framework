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
        // 1. Search Indices Metadata Table
        $db->statement("CREATE TABLE IF NOT EXISTS nf_search_indices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(120) NOT NULL UNIQUE,
            options TEXT NULL,
            created_at DATETIME NOT NULL
        )");

        // 2. Search Documents Table
        $db->statement("CREATE TABLE IF NOT EXISTS nf_search_documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            index_name VARCHAR(120) NOT NULL,
            doc_id VARCHAR(120) NOT NULL,
            fields TEXT NOT NULL,
            vector TEXT NULL,
            boost REAL DEFAULT 1.0,
            created_at DATETIME NOT NULL,
            UNIQUE(index_name, doc_id)
        )");

        // 3. Search Analytics & Telemetry Table
        $db->statement("CREATE TABLE IF NOT EXISTS nf_search_analytics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            index_name VARCHAR(120) NOT NULL,
            query_string VARCHAR(255) NOT NULL,
            hits_count INTEGER NOT NULL DEFAULT 0,
            latency_ms REAL NOT NULL DEFAULT 0.0,
            clicked_doc_id VARCHAR(120) NULL,
            created_at DATETIME NOT NULL
        )");

        $db->statement("CREATE INDEX IF NOT EXISTS idx_search_docs_idx ON nf_search_documents (index_name)");
        $db->statement("CREATE INDEX IF NOT EXISTS idx_search_analytics_idx ON nf_search_analytics (index_name, created_at)");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS nf_search_analytics");
        $db->statement("DROP TABLE IF EXISTS nf_search_documents");
        $db->statement("DROP TABLE IF EXISTS nf_search_indices");
    }
};
