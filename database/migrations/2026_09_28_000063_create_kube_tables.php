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
            CREATE TABLE IF NOT EXISTS kube_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                internal_ip TEXT NOT NULL,
                status TEXT NOT NULL,
                capacity_cpu INTEGER NOT NULL,
                capacity_mem INTEGER NOT NULL,
                labels TEXT,
                taints TEXT,
                created_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS kube_pods (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uid TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                namespace TEXT NOT NULL,
                node_name TEXT,
                phase TEXT NOT NULL,
                ip TEXT,
                labels TEXT,
                restart_policy TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS kube_deployments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                namespace TEXT NOT NULL,
                replicas INTEGER NOT NULL,
                match_labels TEXT,
                strategy TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                UNIQUE(namespace, name)
            );

            CREATE TABLE IF NOT EXISTS kube_services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                namespace TEXT NOT NULL,
                type TEXT NOT NULL,
                cluster_ip TEXT,
                ports TEXT,
                selector TEXT,
                created_at INTEGER NOT NULL,
                UNIQUE(namespace, name)
            );

            CREATE TABLE IF NOT EXISTS kube_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                resource_kind TEXT NOT NULL,
                resource_name TEXT NOT NULL,
                namespace TEXT NOT NULL,
                reason TEXT NOT NULL,
                message TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_kube_pods_ns_name ON kube_pods(namespace, name);
            CREATE INDEX IF NOT EXISTS idx_kube_deployments_ns_name ON kube_deployments(namespace, name);
            CREATE INDEX IF NOT EXISTS idx_kube_services_ns_name ON kube_services(namespace, name);
            CREATE INDEX IF NOT EXISTS idx_kube_events_resource ON kube_events(resource_kind, resource_name);
        ");
    }

    public function down(Connection $db): void
    {
        $db->getPdo()->exec("
            DROP TABLE IF EXISTS kube_events;
            DROP TABLE IF EXISTS kube_services;
            DROP TABLE IF EXISTS kube_deployments;
            DROP TABLE IF EXISTS kube_pods;
            DROP TABLE IF EXISTS kube_nodes;
        ");
    }
};
