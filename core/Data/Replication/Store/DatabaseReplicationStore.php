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

namespace NetFusion\Core\Data\Replication\Store;

use PDO;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Data\Replication\ReplicationEvent;

class DatabaseReplicationStore
{
    protected PDO $pdo;
    protected string $table;

    public function __construct(PDO|Connection $connection, string $table = 'netfusion_replication_changelog')
    {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
        $this->table = $table;
    }

    public function record(ReplicationEvent $event): bool
    {
        $sql = "INSERT INTO {$this->table} (
            id, origin_region, entity_type, entity_id, operation, payload, vector_clock, timestamp, status, created_at
        ) VALUES (
            :id, :origin_region, :entity_type, :entity_id, :operation, :payload, :vector_clock, :timestamp, :status, :created_at
        )";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $event->id,
            'origin_region' => $event->originRegion,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'operation' => $event->operation,
            'payload' => json_encode($event->payload),
            'vector_clock' => json_encode($event->vectorClock->toArray()),
            'timestamp' => $event->timestamp,
            'status' => $event->status,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return ReplicationEvent[]
     */
    public function getPending(?int $limit = 100): array
    {
        $limitClause = $limit ? "LIMIT {$limit}" : '';
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} WHERE status = 'pending' ORDER BY timestamp ASC {$limitClause}");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->hydrate($row);
        }
        return $events;
    }

    /**
     * @return ReplicationEvent[]
     */
    public function getSince(float $timestamp): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE timestamp >= :ts ORDER BY timestamp ASC");
        $stmt->execute(['ts' => $timestamp]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($rows as $row) {
            $events[] = $this->hydrate($row);
        }
        return $events;
    }

    public function markSynced(string $eventId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET status = 'synced' WHERE id = :id");
        return $stmt->execute(['id' => $eventId]);
    }

    public function count(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->table}");
        return (int)($stmt ? $stmt->fetchColumn() : 0);
    }

    protected function hydrate(array $row): ReplicationEvent
    {
        return ReplicationEvent::fromArray([
            'id' => $row['id'],
            'origin_region' => $row['origin_region'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'],
            'operation' => $row['operation'],
            'payload' => json_decode($row['payload'], true) ?: [],
            'vector_clock' => json_decode($row['vector_clock'], true) ?: [],
            'timestamp' => (float)$row['timestamp'],
            'status' => $row['status'],
        ]);
    }
}
