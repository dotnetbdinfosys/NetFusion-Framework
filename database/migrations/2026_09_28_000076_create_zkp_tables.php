<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - ZKP Tables Migration
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

use NetFusion\Core\Database\Connection;

return new class {
    /**
     * Run the migrations.
     */
    public function up(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS zkp_circuits (
                id VARCHAR(191) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                constraints_count INTEGER DEFAULT 0,
                public_signals TEXT NOT NULL,
                proving_key TEXT NOT NULL,
                verifying_key TEXT NOT NULL,
                created_at DATETIME NOT NULL
            );");

            $pdo->exec("CREATE TABLE IF NOT EXISTS zkp_proofs (
                id VARCHAR(191) PRIMARY KEY,
                circuit_id VARCHAR(191) NOT NULL,
                proof_data TEXT NOT NULL,
                public_inputs TEXT NOT NULL,
                verified INTEGER DEFAULT 0,
                created_at DATETIME NOT NULL
            );");

            $pdo->exec("CREATE TABLE IF NOT EXISTS zkp_credentials (
                id VARCHAR(191) PRIMARY KEY,
                issuer VARCHAR(191) NOT NULL,
                subject VARCHAR(191) NOT NULL,
                commitments TEXT NOT NULL,
                issued_at DATETIME NOT NULL
            );");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS zkp_circuits (
                id VARCHAR(191) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                constraints_count INT DEFAULT 0,
                public_signals TEXT NOT NULL,
                proving_key LONGTEXT NOT NULL,
                verifying_key LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_zkp_circuit_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS zkp_proofs (
                id VARCHAR(191) PRIMARY KEY,
                circuit_id VARCHAR(191) NOT NULL,
                proof_data LONGTEXT NOT NULL,
                public_inputs TEXT NOT NULL,
                verified TINYINT(1) DEFAULT 0,
                created_at DATETIME NOT NULL,
                INDEX idx_zkp_proof_circuit (circuit_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS zkp_credentials (
                id VARCHAR(191) PRIMARY KEY,
                issuer VARCHAR(191) NOT NULL,
                subject VARCHAR(191) NOT NULL,
                commitments LONGTEXT NOT NULL,
                issued_at DATETIME NOT NULL,
                INDEX idx_zkp_cred_subject (subject)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS zkp_credentials;");
        $pdo->exec("DROP TABLE IF EXISTS zkp_proofs;");
        $pdo->exec("DROP TABLE IF EXISTS zkp_circuits;");
    }
};
