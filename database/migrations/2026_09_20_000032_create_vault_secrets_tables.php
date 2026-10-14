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

        // 1. Vault Secrets Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_vault_secrets (
            id VARCHAR(64) PRIMARY KEY,
            path VARCHAR(255) NOT NULL,
            version INTEGER NOT NULL DEFAULT 1,
            encrypted_payload TEXT NOT NULL,
            metadata_json TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )");

        // 2. Vault Ephemeral Leases Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS netfusion_vault_leases (
            id VARCHAR(64) PRIMARY KEY,
            path VARCHAR(255) NOT NULL,
            encrypted_credentials TEXT NOT NULL,
            expires_at DOUBLE NOT NULL,
            ttl INTEGER NOT NULL,
            renewable INTEGER NOT NULL DEFAULT 1,
            renew_count INTEGER NOT NULL DEFAULT 0,
            revoked INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )");

        // Indexes for fast lookup
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_vault_path_ver ON netfusion_vault_secrets (path, version)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_vault_leases_exp ON netfusion_vault_leases (expires_at, revoked)");
        } catch (\Throwable) {
            // Index fallback
        }
    }

    public function down(PDO|Connection $connection): void
    {
        $pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;

        $pdo->exec("DROP TABLE IF EXISTS netfusion_vault_leases");
        $pdo->exec("DROP TABLE IF EXISTS netfusion_vault_secrets");
    }
};
