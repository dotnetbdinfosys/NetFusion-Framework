<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $db): void
    {
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS wasm_modules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                hash TEXT NOT NULL,
                bytecode_size INTEGER NOT NULL,
                bytecode BLOB NOT NULL,
                exports TEXT,
                created_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS wasm_execution_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module_name TEXT NOT NULL,
                function_name TEXT NOT NULL,
                duration_ms REAL NOT NULL,
                fuel_consumed INTEGER NOT NULL,
                success INTEGER NOT NULL,
                error TEXT,
                created_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_wasm_modules_name ON wasm_modules(name);
            CREATE INDEX IF NOT EXISTS idx_wasm_exec_logs_module ON wasm_execution_logs(module_name);
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("
            DROP TABLE IF EXISTS wasm_execution_logs;
            DROP TABLE IF EXISTS wasm_modules;
        ");
    }
};
