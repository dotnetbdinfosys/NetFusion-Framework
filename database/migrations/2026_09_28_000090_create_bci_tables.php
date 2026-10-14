<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000090_create_bci_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS bci_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id VARCHAR(191) NOT NULL UNIQUE,
            modality VARCHAR(64) NOT NULL DEFAULT 'motor_imagery',
            sampling_rate REAL NOT NULL DEFAULT 250.0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS bci_channel_streams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id VARCHAR(191) NOT NULL,
            channel_name VARCHAR(32) NOT NULL,
            sample_count INTEGER NOT NULL DEFAULT 0,
            impedance_kohms REAL NOT NULL DEFAULT 5.0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS bci_feedback_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id VARCHAR(191) NOT NULL,
            mental_state VARCHAR(64) NOT NULL,
            relaxation_index REAL NOT NULL,
            focus_index REAL NOT NULL,
            feedback_level REAL NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS bci_feedback_events");
        $pdo->exec("DROP TABLE IF EXISTS bci_channel_streams");
        $pdo->exec("DROP TABLE IF EXISTS bci_sessions");
    }
};
