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

declare(strict_types=1);

use NetFusion\Core\Database\Connection;

return new class {
    public function up(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        // 1. Service Mesh Endpoints & Instances Registry
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_mesh_services (
            instance_id VARCHAR(128) PRIMARY KEY,
            service_name VARCHAR(128) NOT NULL,
            host VARCHAR(128) NOT NULL,
            port INTEGER NOT NULL,
            subset VARCHAR(64) NOT NULL DEFAULT 'v1',
            weight INTEGER NOT NULL DEFAULT 100,
            status VARCHAR(32) NOT NULL DEFAULT 'HEALTHY',
            spiffe_id VARCHAR(255),
            metadata_json TEXT,
            updated_at DATETIME NOT NULL
        )");

        // 2. Traffic Routes & Canary Splitting Configurations
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_mesh_routes (
            name VARCHAR(128) PRIMARY KEY,
            service_name VARCHAR(128) NOT NULL,
            config_json TEXT NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 3. Zero-Trust Mesh Authorization Policies
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_mesh_policies (
            name VARCHAR(128) PRIMARY KEY,
            action VARCHAR(16) NOT NULL DEFAULT 'ALLOW',
            config_json TEXT NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 4. Mesh mTLS Certificates & SPIFFE Identity Store
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_mesh_certificates (
            spiffe_id VARCHAR(255) PRIMARY KEY,
            cert_pem TEXT NOT NULL,
            key_pem TEXT NOT NULL,
            ca_pem TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 5. Mesh Metrics & Latency Telemetry
        $pdo->exec("CREATE TABLE IF NOT EXISTS nf_mesh_metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            metric_name VARCHAR(128) NOT NULL,
            metric_value DOUBLE NOT NULL,
            tags TEXT,
            recorded_at DATETIME NOT NULL
        )");

        // Indices
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mesh_services_name ON nf_mesh_services(service_name)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mesh_services_status ON nf_mesh_services(status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mesh_metrics_name ON nf_mesh_metrics(metric_name, recorded_at)");
        } catch (\Throwable) {
            // Indices already exist or not supported
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS nf_mesh_metrics");
        $pdo->exec("DROP TABLE IF EXISTS nf_mesh_certificates");
        $pdo->exec("DROP TABLE IF EXISTS nf_mesh_policies");
        $pdo->exec("DROP TABLE IF EXISTS nf_mesh_routes");
        $pdo->exec("DROP TABLE IF EXISTS nf_mesh_services");
    }
};
