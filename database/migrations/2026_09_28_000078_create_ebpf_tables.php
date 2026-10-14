<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - eBPF & Network Observability Migration
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
            $pdo->exec("CREATE TABLE IF NOT EXISTS ebpf_programs (
                id VARCHAR(191) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(50) NOT NULL,
                bytecode_size INTEGER NOT NULL,
                verified INTEGER DEFAULT 0,
                attached_interface VARCHAR(50) DEFAULT NULL,
                created_at DATETIME NOT NULL
            );");

            $pdo->exec("CREATE TABLE IF NOT EXISTS ebpf_maps (
                id VARCHAR(191) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(50) NOT NULL,
                max_entries INTEGER NOT NULL,
                current_entries INTEGER DEFAULT 0,
                created_at DATETIME NOT NULL
            );");

            $pdo->exec("CREATE TABLE IF NOT EXISTS network_flow_metrics (
                id VARCHAR(191) PRIMARY KEY,
                flow_id VARCHAR(191) NOT NULL,
                src_endpoint VARCHAR(100) NOT NULL,
                dst_endpoint VARCHAR(100) NOT NULL,
                protocol VARCHAR(20) NOT NULL,
                packet_count INTEGER DEFAULT 0,
                byte_count INTEGER DEFAULT 0,
                rtt_ms REAL DEFAULT 0,
                retransmits INTEGER DEFAULT 0,
                created_at DATETIME NOT NULL
            );");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ebpf_programs (
                id VARCHAR(191) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(50) NOT NULL,
                bytecode_size INT NOT NULL,
                verified INT DEFAULT 0,
                attached_interface VARCHAR(50) DEFAULT NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS ebpf_maps (
                id VARCHAR(191) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(50) NOT NULL,
                max_entries INT NOT NULL,
                current_entries INT DEFAULT 0,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS network_flow_metrics (
                id VARCHAR(191) PRIMARY KEY,
                flow_id VARCHAR(191) NOT NULL,
                src_endpoint VARCHAR(100) NOT NULL,
                dst_endpoint VARCHAR(100) NOT NULL,
                protocol VARCHAR(20) NOT NULL,
                packet_count INT DEFAULT 0,
                byte_count INT DEFAULT 0,
                rtt_ms DOUBLE DEFAULT 0,
                retransmits INT DEFAULT 0,
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
        $pdo->exec("DROP TABLE IF EXISTS network_flow_metrics;");
        $pdo->exec("DROP TABLE IF EXISTS ebpf_maps;");
        $pdo->exec("DROP TABLE IF EXISTS ebpf_programs;");
    }
};
