<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework - ZooKeeper Tables Migration
 |--------------------------------------------------------------------------
 |
 | Creates persistence tables for ZooKeeper znodes, transaction logs (zxids),
 | and active client sessions.
 |
 */

declare(strict_types=1);

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $db): void
    {
        $db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS zookeeper_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                path TEXT NOT NULL UNIQUE,
                data TEXT,
                mode INTEGER NOT NULL DEFAULT 0,
                czxid INTEGER NOT NULL,
                mzxid INTEGER NOT NULL,
                ctime INTEGER NOT NULL,
                mtime INTEGER NOT NULL,
                version INTEGER NOT NULL DEFAULT 0,
                cversion INTEGER NOT NULL DEFAULT 0,
                ephemeral_owner INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_zk_nodes_path ON zookeeper_nodes(path);

            CREATE TABLE IF NOT EXISTS zookeeper_txns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                zxid INTEGER NOT NULL UNIQUE,
                op TEXT NOT NULL,
                path TEXT NOT NULL,
                data TEXT,
                created_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS zookeeper_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL UNIQUE,
                timeout_ms INTEGER NOT NULL,
                password TEXT NOT NULL,
                last_heartbeat REAL NOT NULL,
                closed INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL
            );
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("
            DROP TABLE IF EXISTS zookeeper_sessions;
            DROP TABLE IF EXISTS zookeeper_txns;
            DROP TABLE IF EXISTS zookeeper_nodes;
        ");
    }
};
