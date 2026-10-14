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
            CREATE TABLE IF NOT EXISTS etcd_revisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT NOT NULL,
                create_revision INTEGER NOT NULL,
                mod_revision INTEGER NOT NULL,
                version INTEGER NOT NULL,
                value TEXT,
                lease_id INTEGER DEFAULT 0,
                is_tombstone INTEGER DEFAULT 0,
                created_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_etcd_key ON etcd_revisions(key);
            CREATE INDEX IF NOT EXISTS idx_etcd_mod_rev ON etcd_revisions(mod_revision);

            CREATE TABLE IF NOT EXISTS etcd_leases (
                id INTEGER PRIMARY KEY,
                ttl INTEGER NOT NULL,
                granted_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("
            DROP TABLE IF EXISTS etcd_revisions;
            DROP TABLE IF EXISTS etcd_leases;
        ");
    }
};
