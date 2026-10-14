<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_18_000021_create_locks_table.php
| Module    : Lock
| Namespace : N/A (Migration)
| Purpose   : Creates netfusion_locks table for distributed atomic locking.
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

use NetFusion\Core\Database\Connection;

return new class {
    /**
     * Run the migration.
     */
    public function up(Connection $conn): void
    {
        $conn->statement("
            CREATE TABLE IF NOT EXISTS netfusion_locks (
                key         TEXT    NOT NULL PRIMARY KEY,
                owner       TEXT    NOT NULL,
                expiration  INTEGER NOT NULL,
                created_at  TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at  TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_netfusion_locks_expiration
                ON netfusion_locks (expiration)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(Connection $conn): void
    {
        $conn->statement("DROP TABLE IF EXISTS netfusion_locks");
    }
};