<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/EventStore.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : Database-backed Event Store with Optimistic Concurrency Control.
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

namespace NetFusion\Core\EventSourcing;

use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\EventSourcing\Contracts\DomainEventInterface;
use NetFusion\Core\EventSourcing\Contracts\EventStoreInterface;
use NetFusion\Core\EventSourcing\Exceptions\ConcurrencyException;
use PDO;

class EventStore implements EventStoreInterface
{
    public function __construct(
        protected DatabaseManager $db,
        protected ?ProjectionManager $projections = null,
        protected string $streamsTable = 'event_streams',
        protected string $eventsTable = 'stored_events'
    ) {
    }

    public function append(string $streamId, string $aggregateType, array $events, int $expectedVersion = -1): void
    {
        if (empty($events)) {
            return;
        }

        $pdo = $this->db->connection()->getPdo();
        $inTransaction = $pdo->inTransaction();

        if (!$inTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // Check current stream version
            $stmt = $pdo->prepare("SELECT current_version FROM {$this->streamsTable} WHERE stream_id = ?");
            $stmt->execute([$streamId]);
            $versionVal = $stmt->fetchColumn();

            $streamExists = ($versionVal !== false);
            $currentVersion = $streamExists ? (int)$versionVal : 0;

            if ($expectedVersion !== -1 && $currentVersion !== $expectedVersion) {
                throw ConcurrencyException::versionMismatch($streamId, $expectedVersion, $currentVersion);
            }

            $nextVersion = $currentVersion;
            $insertEventStmt = $pdo->prepare(
                "INSERT INTO {$this->eventsTable} (event_id, stream_id, aggregate_type, version, event_name, payload, metadata, occurred_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $storedEvents = [];

            foreach ($events as $event) {
                if (!$event instanceof DomainEventInterface) {
                    continue;
                }

                $nextVersion++;
                $event = $event->withStreamId($streamId)
                    ->withAggregateType($aggregateType)
                    ->withVersion($nextVersion);

                $insertEventStmt->execute([
                    $event->getEventId(),
                    $streamId,
                    $aggregateType,
                    $nextVersion,
                    get_class($event),
                    json_encode($event->getPayload(), JSON_UNESCAPED_UNICODE),
                    json_encode($event->getMetadata(), JSON_UNESCAPED_UNICODE),
                    $event->getOccurredAt(),
                ]);

                $storedEvents[] = $event;
            }

            // Upsert stream header
            if (!$streamExists) {
                $upsertStream = $pdo->prepare(
                    "INSERT INTO {$this->streamsTable} (stream_id, aggregate_type, current_version, created_at, updated_at) VALUES (?, ?, ?, ?, ?)"
                );
                $now = date('Y-m-d H:i:s');
                $upsertStream->execute([$streamId, $aggregateType, $nextVersion, $now, $now]);
            } else {
                $upsertStream = $pdo->prepare(
                    "UPDATE {$this->streamsTable} SET current_version = ?, updated_at = ? WHERE stream_id = ?"
                );
                $upsertStream->execute([$nextVersion, date('Y-m-d H:i:s'), $streamId]);
            }

            if (!$inTransaction) {
                $pdo->commit();
            }

            // Project to Read Models
            if ($this->projections !== null) {
                foreach ($storedEvents as $ev) {
                    $this->projections->project($ev);
                }
            }
        } catch (\Throwable $e) {
            if (!$inTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function loadStream(string $streamId, int $fromVersion = 0): EventStream
    {
        $pdo = $this->db->connection()->getPdo();

        $stmt = $pdo->prepare("SELECT aggregate_type, current_version FROM {$this->streamsTable} WHERE stream_id = ?");
        $stmt->execute([$streamId]);
        $streamRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $aggregateType = $streamRow ? (string)$streamRow['aggregate_type'] : '';
        $currentVersion = $streamRow ? (int)$streamRow['current_version'] : 0;

        $eventStmt = $pdo->prepare(
            "SELECT * FROM {$this->eventsTable} WHERE stream_id = ? AND version >= ? ORDER BY version ASC"
        );
        $eventStmt->execute([$streamId, $fromVersion]);
        $rows = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($rows as $row) {
            $eventClass = $row['event_name'];
            $payload = json_decode((string)$row['payload'], true) ?: [];
            $metadata = json_decode((string)$row['metadata'], true) ?: [];

            if (class_exists($eventClass)) {
                $event = new $eventClass($payload, $metadata, (string)$row['event_id'], (string)$row['occurred_at']);
            } else {
                // Generic fallback anonymous event
                $event = new class($payload, $metadata, (string)$row['event_id'], (string)$row['occurred_at']) extends DomainEvent {};
            }

            $event = $event->withStreamId((string)$row['stream_id'])
                ->withAggregateType((string)$row['aggregate_type'])
                ->withVersion((int)$row['version']);

            $events[] = $event;
        }

        return new EventStream($streamId, $aggregateType, $events, $currentVersion);
    }

    public function getStreams(?string $aggregateType = null): array
    {
        $pdo = $this->db->connection()->getPdo();

        if ($aggregateType !== null) {
            $stmt = $pdo->prepare("SELECT * FROM {$this->streamsTable} WHERE aggregate_type = ? ORDER BY id DESC");
            $stmt->execute([$aggregateType]);
        } else {
            $stmt = $pdo->query("SELECT * FROM {$this->streamsTable} ORDER BY id DESC");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replayAll(array $projections = []): int
    {
        $pdo = $this->db->connection()->getPdo();
        $stmt = $pdo->query("SELECT * FROM {$this->eventsTable} ORDER BY id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $targetProjections = !empty($projections) ? $projections : ($this->projections ? $this->projections->all() : []);

        // Reset target projections first
        foreach ($targetProjections as $proj) {
            $proj->reset();
        }

        $count = 0;
        foreach ($rows as $row) {
            $eventClass = $row['event_name'];
            $payload = json_decode((string)$row['payload'], true) ?: [];
            $metadata = json_decode((string)$row['metadata'], true) ?: [];

            if (class_exists($eventClass)) {
                $event = new $eventClass($payload, $metadata, (string)$row['event_id'], (string)$row['occurred_at']);
            } else {
                $event = new class($payload, $metadata, (string)$row['event_id'], (string)$row['occurred_at']) extends DomainEvent {};
            }

            $event = $event->withStreamId((string)$row['stream_id'])
                ->withAggregateType((string)$row['aggregate_type'])
                ->withVersion((int)$row['version']);

            foreach ($targetProjections as $proj) {
                $proj->project($event);
            }
            $count++;
        }

        return $count;
    }

    public function replayStream(string $streamId, array $projections = []): int
    {
        $stream = $this->loadStream($streamId);
        $targetProjections = !empty($projections) ? $projections : ($this->projections ? $this->projections->all() : []);

        $count = 0;
        foreach ($stream->getEvents() as $event) {
            foreach ($targetProjections as $proj) {
                $proj->project($event);
            }
            $count++;
        }

        return $count;
    }
}