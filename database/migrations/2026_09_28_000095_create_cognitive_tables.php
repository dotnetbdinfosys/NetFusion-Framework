<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000095_create_cognitive_tables.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $conn): void
    {
        $pdo = $conn->getPdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS cognitive_chunks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chunk_id VARCHAR(191) NOT NULL UNIQUE,
            concept_type VARCHAR(64) NOT NULL,
            slots TEXT NOT NULL,
            access_count INTEGER NOT NULL DEFAULT 1,
            base_activation REAL NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cognitive_episodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            episode_id VARCHAR(191) NOT NULL UNIQUE,
            action VARCHAR(128) NOT NULL,
            outcome TEXT,
            tags TEXT,
            occurred_at REAL NOT NULL,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cognitive_chunks_type ON cognitive_chunks (concept_type)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS cognitive_episodes");
        $pdo->exec("DROP TABLE IF EXISTS cognitive_chunks");
    }
};
