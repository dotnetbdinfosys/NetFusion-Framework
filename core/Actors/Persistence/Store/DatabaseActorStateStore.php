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

namespace NetFusion\Core\Actors\Persistence\Store;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Actors\Persistence\Contracts\ActorStateStoreInterface;
use PDO;

class DatabaseActorStateStore implements ActorStateStoreInterface
{
    protected PDO $pdo;

    public function __construct(PDO|Connection $connection)
    {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
    }

    public function saveSnapshot(string $persistenceId, int $sequenceNumber, mixed $state): bool
    {
        $stateJson = json_encode($state);
        $now = microtime(true);

        $stmt = $this->pdo->prepare("
            INSERT INTO netfusion_actor_snapshots (
                persistence_id, sequence_number, state_data, updated_at
            ) VALUES (
                :persistence_id, :sequence_number, :state_data, :updated_at
            )
            ON CONFLICT(persistence_id) DO UPDATE SET
                sequence_number = excluded.sequence_number,
                state_data = excluded.state_data,
                updated_at = excluded.updated_at
        ");

        try {
            return $stmt->execute([
                ':persistence_id' => $persistenceId,
                ':sequence_number' => $sequenceNumber,
                ':state_data' => $stateJson,
                ':updated_at' => $now,
            ]);
        } catch (\Throwable) {
            $mysqlStmt = $this->pdo->prepare("
                REPLACE INTO netfusion_actor_snapshots (
                    persistence_id, sequence_number, state_data, updated_at
                ) VALUES (
                    :persistence_id, :sequence_number, :state_data, :updated_at
                )
            ");
            return $mysqlStmt->execute([
                ':persistence_id' => $persistenceId,
                ':sequence_number' => $sequenceNumber,
                ':state_data' => $stateJson,
                ':updated_at' => $now,
            ]);
        }
    }

    public function loadSnapshot(string $persistenceId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM netfusion_actor_snapshots WHERE persistence_id = :id LIMIT 1");
        $stmt->execute([':id' => $persistenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'sequence' => (int)$row['sequence_number'],
            'state' => json_decode((string)$row['state_data'], true) ?? $row['state_data'],
            'timestamp' => (float)$row['updated_at'],
        ];
    }

    public function appendEvent(string $persistenceId, int $sequenceNumber, mixed $event): bool
    {
        $id = 'evt_' . bin2hex(random_bytes(8));
        $stmt = $this->pdo->prepare("
            INSERT INTO netfusion_actor_events (
                id, persistence_id, sequence_number, event_data, created_at
            ) VALUES (
                :id, :persistence_id, :sequence_number, :event_data, :created_at
            )
        ");

        return $stmt->execute([
            ':id' => $id,
            ':persistence_id' => $persistenceId,
            ':sequence_number' => $sequenceNumber,
            ':event_data' => json_encode($event),
            ':created_at' => microtime(true),
        ]);
    }

    public function getEvents(string $persistenceId, int $fromSequence = 1): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM netfusion_actor_events
            WHERE persistence_id = :id AND sequence_number >= :seq
            ORDER BY sequence_number ASC
        ");
        $stmt->execute([
            ':id' => $persistenceId,
            ':seq' => $fromSequence,
        ]);

        $events = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $events[] = [
                'sequence' => (int)$row['sequence_number'],
                'event' => json_decode((string)$row['event_data'], true) ?? $row['event_data'],
                'timestamp' => (float)$row['created_at'],
            ];
        }
        return $events;
    }

    public function purge(string $persistenceId): bool
    {
        $s1 = $this->pdo->prepare("DELETE FROM netfusion_actor_events WHERE persistence_id = :id");
        $s1->execute([':id' => $persistenceId]);

        $s2 = $this->pdo->prepare("DELETE FROM netfusion_actor_snapshots WHERE persistence_id = :id");
        return $s2->execute([':id' => $persistenceId]);
    }
}
