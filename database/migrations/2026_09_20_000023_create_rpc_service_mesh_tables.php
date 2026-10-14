<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : database/migrations/2026_09_20_000023_create_rpc_service_mesh_tables.php
| Module    : RPC & Service Mesh
| Namespace : N/A (Migration)
| Purpose   : Creates netfusion_service_registry for service mesh node discovery.
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
            CREATE TABLE IF NOT EXISTS netfusion_service_registry (
                id             VARCHAR(191) NOT NULL PRIMARY KEY,
                service_name   VARCHAR(191) NOT NULL,
                host           VARCHAR(191) NOT NULL,
                port           INTEGER      NOT NULL,
                metadata       TEXT         NULL,
                status         VARCHAR(50)  NOT NULL DEFAULT 'UP',
                last_heartbeat REAL         NOT NULL,
                created_at     TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now')),
                updated_at     TEXT         NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S', 'now'))
            )
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_rpc_service_name_status
                ON netfusion_service_registry (service_name, status)
        ");

        $conn->statement("
            CREATE INDEX IF NOT EXISTS idx_rpc_last_heartbeat
                ON netfusion_service_registry (last_heartbeat)
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(Connection $conn): void
    {
        $conn->statement("DROP TABLE IF EXISTS netfusion_service_registry");
    }
};
