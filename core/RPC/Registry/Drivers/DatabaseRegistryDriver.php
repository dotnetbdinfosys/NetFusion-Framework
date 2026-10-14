<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Registry/Drivers/DatabaseRegistryDriver.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Registry\Drivers
| Purpose   : Persistent database-backed service registry driver with atomic heartbeats.
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

namespace NetFusion\Core\RPC\Registry\Drivers;

use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\RPC\Contracts\ServiceRegistryInterface;
use NetFusion\Core\RPC\Registry\ServiceInstance;
use PDO;

class DatabaseRegistryDriver implements ServiceRegistryInterface
{
    protected string $table = 'netfusion_service_registry';

    public function __construct(
        protected DatabaseManager|PDO $connection
    ) {}

    protected function getPdo(): PDO
    {
        if ($this->connection instanceof DatabaseManager) {
            return $this->connection->connection()->getPdo();
        }
        return $this->connection;
    }

    public function register(ServiceInstance $instance): void
    {
        $pdo = $this->getPdo();
        $now = date('Y-m-d H:i:s');
        $nowTs = microtime(true);

        $stmt = $pdo->prepare("
            INSERT INTO {$this->table} (id, service_name, host, port, metadata, status, last_heartbeat, created_at, updated_at)
            VALUES (:id, :service_name, :host, :port, :metadata, :status, :last_heartbeat, :created_at, :updated_at)
            ON CONFLICT(id) DO UPDATE SET
                service_name   = excluded.service_name,
                host           = excluded.host,
                port           = excluded.port,
                metadata       = excluded.metadata,
                status         = excluded.status,
                last_heartbeat = excluded.last_heartbeat,
                updated_at     = excluded.updated_at
        ");

        try {
            $stmt->execute([
                ':id'             => $instance->id,
                ':service_name'   => $instance->serviceName,
                ':host'           => $instance->host,
                ':port'           => $instance->port,
                ':metadata'       => json_encode($instance->metadata),
                ':status'         => $instance->status,
                ':last_heartbeat' => $nowTs,
                ':created_at'     => $now,
                ':updated_at'     => $now,
            ]);
        } catch (\Throwable) {
            // Fallback for MySQL ON DUPLICATE KEY UPDATE if needed
            $stmtFallback = $pdo->prepare("
                INSERT INTO {$this->table} (id, service_name, host, port, metadata, status, last_heartbeat, created_at, updated_at)
                VALUES (:id, :service_name, :host, :port, :metadata, :status, :last_heartbeat, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    service_name   = VALUES(service_name),
                    host           = VALUES(host),
                    port           = VALUES(port),
                    metadata       = VALUES(metadata),
                    status         = VALUES(status),
                    last_heartbeat = VALUES(last_heartbeat),
                    updated_at     = VALUES(updated_at)
            ");
            $stmtFallback->execute([
                ':id'             => $instance->id,
                ':service_name'   => $instance->serviceName,
                ':host'           => $instance->host,
                ':port'           => $instance->port,
                ':metadata'       => json_encode($instance->metadata),
                ':status'         => $instance->status,
                ':last_heartbeat' => $nowTs,
                ':created_at'     => $now,
                ':updated_at'     => $now,
            ]);
        }
    }

    public function deregister(string $instanceId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $instanceId]);
    }

    public function heartbeat(string $instanceId): bool
    {
        $pdo = $this->getPdo();
        $now = date('Y-m-d H:i:s');
        $nowTs = microtime(true);

        $stmt = $pdo->prepare("
            UPDATE {$this->table}
            SET last_heartbeat = :last_heartbeat, status = 'UP', updated_at = :updated_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':id'             => $instanceId,
            ':last_heartbeat' => $nowTs,
            ':updated_at'     => $now,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function discover(string $serviceName): array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE service_name = :service_name AND status = 'UP'
        ");
        $stmt->execute([':service_name' => $serviceName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $instances = [];
        foreach ($rows as $row) {
            $metadata = !empty($row['metadata']) ? json_decode((string) $row['metadata'], true) : [];
            $inst = new ServiceInstance(
                id: (string) $row['id'],
                serviceName: (string) $row['service_name'],
                host: (string) $row['host'],
                port: (int) $row['port'],
                metadata: is_array($metadata) ? $metadata : [],
                status: (string) $row['status'],
                lastHeartbeat: (float) $row['last_heartbeat']
            );

            if ($inst->isHealthy()) {
                $instances[] = $inst;
            }
        }

        return $instances;
    }

    public function all(): array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT * FROM {$this->table} ORDER BY service_name, id");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $grouped = [];
        foreach ($rows as $row) {
            $metadata = !empty($row['metadata']) ? json_decode((string) $row['metadata'], true) : [];
            $inst = new ServiceInstance(
                id: (string) $row['id'],
                serviceName: (string) $row['service_name'],
                host: (string) $row['host'],
                port: (int) $row['port'],
                metadata: is_array($metadata) ? $metadata : [],
                status: (string) $row['status'],
                lastHeartbeat: (float) $row['last_heartbeat']
            );

            $grouped[$inst->serviceName][] = $inst;
        }

        return $grouped;
    }

    public function pruneExpired(int $ttlSeconds = 30): int
    {
        $pdo = $this->getPdo();
        $cutoff = microtime(true) - $ttlSeconds;

        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE last_heartbeat < :cutoff");
        $stmt->execute([':cutoff' => $cutoff]);

        return $stmt->rowCount();
    }
}
