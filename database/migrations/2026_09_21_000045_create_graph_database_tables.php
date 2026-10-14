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
        // Nodes table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_graph_nodes (
                id VARCHAR(255) PRIMARY KEY,
                labels TEXT NOT NULL,
                properties TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Edges table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_graph_edges (
                id VARCHAR(255) PRIMARY KEY,
                source_id VARCHAR(255) NOT NULL,
                target_id VARCHAR(255) NOT NULL,
                type VARCHAR(100) NOT NULL,
                directed INTEGER DEFAULT 1,
                weight REAL DEFAULT 1.0,
                properties TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // RDF Triples table
        $db->statement("
            CREATE TABLE IF NOT EXISTS nf_graph_triples (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subject VARCHAR(255) NOT NULL,
                predicate VARCHAR(255) NOT NULL,
                object VARCHAR(255) NOT NULL,
                inferred INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(Connection $db): void
    {
        $db->statement("DROP TABLE IF EXISTS nf_graph_triples");
        $db->statement("DROP TABLE IF EXISTS nf_graph_edges");
        $db->statement("DROP TABLE IF EXISTS nf_graph_nodes");
    }
};
