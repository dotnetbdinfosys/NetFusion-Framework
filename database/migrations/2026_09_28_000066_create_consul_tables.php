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
            CREATE TABLE IF NOT EXISTS consul_services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                instance_id TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                address TEXT NOT NULL,
                port INTEGER NOT NULL,
                tags TEXT,
                meta TEXT,
                weights TEXT,
                status TEXT NOT NULL DEFAULT 'passing',
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_consul_services_name ON consul_services(name);

            CREATE TABLE IF NOT EXISTS consul_checks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                check_id TEXT NOT NULL UNIQUE,
                service_instance_id TEXT NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'passing',
                http_url TEXT,
                tcp_address TEXT,
                interval INTEGER NOT NULL DEFAULT 10,
                timeout INTEGER NOT NULL DEFAULT 5,
                deregister_critical_after INTEGER NOT NULL DEFAULT 30,
                output TEXT,
                last_checked_at REAL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_consul_checks_inst ON consul_checks(service_instance_id);

            CREATE TABLE IF NOT EXISTS consul_cluster_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                node_id TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                address TEXT NOT NULL,
                port INTEGER NOT NULL DEFAULT 8301,
                status TEXT NOT NULL DEFAULT 'alive',
                incarnation INTEGER NOT NULL DEFAULT 0,
                metadata TEXT,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("
            DROP TABLE IF EXISTS consul_cluster_nodes;
            DROP TABLE IF EXISTS consul_checks;
            DROP TABLE IF EXISTS consul_services;
        ");
    }
};
