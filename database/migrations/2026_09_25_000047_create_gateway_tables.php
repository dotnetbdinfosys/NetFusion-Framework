<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

use NetFusion\Core\Database\Connection;

return new class {
    public function up(Connection $db): void
    {
        $pdo = $db->getPdo();

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS nf_gateway_routes (
                id          TEXT PRIMARY KEY,
                path_prefix TEXT NOT NULL,
                hostname    TEXT NOT NULL DEFAULT \'*\',
                config      TEXT NOT NULL,
                created_at  TEXT NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_gw_routes_prefix
                ON nf_gateway_routes (path_prefix);

            CREATE TABLE IF NOT EXISTS nf_gateway_upstream_health (
                upstream_id TEXT PRIMARY KEY,
                healthy     INTEGER NOT NULL DEFAULT 1,
                failures    INTEGER NOT NULL DEFAULT 0,
                checked_at  TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS nf_gateway_rate_buckets (
                bucket_key  TEXT PRIMARY KEY,
                tokens      INTEGER NOT NULL,
                reset_at    INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_gw_rate_reset
                ON nf_gateway_rate_buckets (reset_at);

            CREATE TABLE IF NOT EXISTS nf_gateway_request_log (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                route_id    TEXT    NOT NULL,
                upstream_id TEXT    NOT NULL,
                method      TEXT    NOT NULL,
                path        TEXT    NOT NULL,
                status_code INTEGER NOT NULL,
                latency_ms  REAL    NOT NULL DEFAULT 0,
                client_ip   TEXT    NOT NULL,
                logged_at   TEXT    NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_gw_log_route
                ON nf_gateway_request_log (route_id, logged_at);

            CREATE INDEX IF NOT EXISTS idx_gw_log_status
                ON nf_gateway_request_log (status_code);
        ');
    }

    public function down(Connection $db): void
    {
        $pdo = $db->getPdo();
        $pdo->exec('
            DROP TABLE IF EXISTS nf_gateway_request_log;
            DROP TABLE IF EXISTS nf_gateway_rate_buckets;
            DROP TABLE IF EXISTS nf_gateway_upstream_health;
            DROP TABLE IF EXISTS nf_gateway_routes;
        ');
    }
};
