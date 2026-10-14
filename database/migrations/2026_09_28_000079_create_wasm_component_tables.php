<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : database/migrations/2026_09_28_000079_create_wasm_component_tables.php
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS wasm_components (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            component_name VARCHAR(191) NOT NULL UNIQUE,
            version VARCHAR(50) NOT NULL DEFAULT '0.1.0',
            wit_specification TEXT NULL,
            binary_size INTEGER NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS wasm_component_pipelines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pipeline_name VARCHAR(191) NOT NULL UNIQUE,
            stages_json TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS wasm_component_execution_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            component_name VARCHAR(191) NOT NULL,
            function_name VARCHAR(191) NOT NULL,
            duration_ms REAL NOT NULL DEFAULT 0.0,
            memory_bytes INTEGER NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'success',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS wasm_component_execution_logs");
        $pdo->exec("DROP TABLE IF EXISTS wasm_component_pipelines");
        $pdo->exec("DROP TABLE IF EXISTS wasm_components");
    }
};
