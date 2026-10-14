<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Formal Verification Tables Migration
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
            $pdo->exec("CREATE TABLE IF NOT EXISTS formal_verifications (
                id VARCHAR(191) PRIMARY KEY,
                target_type VARCHAR(100) NOT NULL,
                target_name VARCHAR(191) NOT NULL,
                status VARCHAR(50) NOT NULL,
                proof_result TEXT NOT NULL,
                execution_time_ms REAL DEFAULT 0,
                created_at DATETIME NOT NULL
            );");

            $pdo->exec("CREATE TABLE IF NOT EXISTS formal_solver_benchmarks (
                id VARCHAR(191) PRIMARY KEY,
                logic_type VARCHAR(100) NOT NULL,
                clauses_count INTEGER DEFAULT 0,
                variables_count INTEGER DEFAULT 0,
                status VARCHAR(50) NOT NULL,
                duration_ms REAL DEFAULT 0,
                created_at DATETIME NOT NULL
            );");

            $pdo->exec("CREATE TABLE IF NOT EXISTS formal_symbolic_traces (
                id VARCHAR(191) PRIMARY KEY,
                method_signature VARCHAR(191) NOT NULL,
                paths_explored INTEGER DEFAULT 0,
                bugs_found INTEGER DEFAULT 0,
                details TEXT NOT NULL,
                created_at DATETIME NOT NULL
            );");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS formal_verifications (
                id VARCHAR(191) PRIMARY KEY,
                target_type VARCHAR(100) NOT NULL,
                target_name VARCHAR(191) NOT NULL,
                status VARCHAR(50) NOT NULL,
                proof_result TEXT NOT NULL,
                execution_time_ms DOUBLE DEFAULT 0,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS formal_solver_benchmarks (
                id VARCHAR(191) PRIMARY KEY,
                logic_type VARCHAR(100) NOT NULL,
                clauses_count INT DEFAULT 0,
                variables_count INT DEFAULT 0,
                status VARCHAR(50) NOT NULL,
                duration_ms DOUBLE DEFAULT 0,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS formal_symbolic_traces (
                id VARCHAR(191) PRIMARY KEY,
                method_signature VARCHAR(191) NOT NULL,
                paths_explored INT DEFAULT 0,
                bugs_found INT DEFAULT 0,
                details TEXT NOT NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $conn): void
    {
        $pdo = $conn->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS formal_symbolic_traces;");
        $pdo->exec("DROP TABLE IF EXISTS formal_solver_benchmarks;");
        $pdo->exec("DROP TABLE IF EXISTS formal_verifications;");
    }
};
