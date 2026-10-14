<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000098_create_biocad_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS biocad_crispr_guides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            guide_id VARCHAR(191) NOT NULL UNIQUE,
            target_locus VARCHAR(255) NOT NULL,
            spacer_seq VARCHAR(64) NOT NULL,
            pam_motif VARCHAR(32) NOT NULL,
            cas_variant VARCHAR(32) NOT NULL,
            gc_content REAL NOT NULL,
            position INTEGER NOT NULL,
            strand VARCHAR(16) NOT NULL DEFAULT 'forward',
            on_target_score REAL NOT NULL DEFAULT 0.0,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS biocad_circuits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            circuit_id VARCHAR(191) NOT NULL UNIQUE,
            circuit_name VARCHAR(255) NOT NULL,
            circuit_type VARCHAR(64) NOT NULL,
            parts_count INTEGER NOT NULL DEFAULT 0,
            state_snapshot TEXT,
            created_at DATETIME NOT NULL
        )");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_biocad_guides_locus ON biocad_crispr_guides (target_locus)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_biocad_circuits_type ON biocad_circuits (circuit_type)");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS biocad_circuits");
        $pdo->exec("DROP TABLE IF EXISTS biocad_crispr_guides");
    }
};
