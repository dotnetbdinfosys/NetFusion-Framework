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

namespace NetFusion\Core\DistributedDb\MultiMaster;

/**
 * Geo-distributed database master node maintaining local storage and replication streams.
 */
class GeoRegionNode
{
    private string $regionId;

    /**
     * @var array<string, array<string, array{data: array, vector_clock: array, timestamp: int, deleted: bool, last_event: ReplicationEvent}>>
     */
    private array $tables = [];

    /**
     * @var array<string, int> Region vector clock
     */
    private array $vectorClock = [];

    private int $logicalClock = 0;

    /**
     * @var array<string, array<ReplicationEvent>> Outbox queues grouped by destination region
     */
    private array $outbox = [];

    /**
     * @var array<ReplicationEvent> Local mutation journal
     */
    private array $mutationJournal = [];

    /**
     * @var array<array{local: ReplicationEvent, remote: ReplicationEvent, resolved: ReplicationEvent, timestamp: int}>
     */
    private array $conflictHistory = [];

    public function __construct(string $regionId)
    {
        $this->regionId = $regionId;
        $this->vectorClock = [$regionId => 0];
    }

    public function getRegionId(): string
    {
        return $this->regionId;
    }

    public function getVectorClock(): array
    {
        return $this->vectorClock;
    }

    public function getTableData(string $table): array
    {
        $result = [];
        if (!isset($this->tables[$table])) {
            return [];
        }

        foreach ($this->tables[$table] as $pk => $row) {
            if (!$row['deleted']) {
                $result[$pk] = $row['data'];
            }
        }

        return $result;
    }

    public function getRow(string $table, mixed $pk): ?array
    {
        $strPk = (string) $pk;
        if (!isset($this->tables[$table][$strPk])) {
            return null;
        }

        $row = $this->tables[$table][$strPk];
        return $row['deleted'] ? null : $row['data'];
    }

    /**
     * Perform local write (INSERT / UPDATE / DELETE).
     *
     * @param string $table
     * @param mixed $pk
     * @param array<string, mixed> $data
     * @param string $op
     * @param array<string> $peerRegions
     * @return ReplicationEvent
     */
    public function write(
        string $table,
        mixed $pk,
        array $data,
        string $op = 'UPDATE',
        array $peerRegions = []
    ): ReplicationEvent {
        $strPk = (string) $pk;
        $this->vectorClock[$this->regionId] = ($this->vectorClock[$this->regionId] ?? 0) + 1;
        $this->logicalClock++;
        $now = (int) (microtime(true) * 1000000);

        $oldData = $this->getRow($table, $pk) ?? [];
        $isDelete = ($op === 'DELETE');
        $newData = $isDelete ? [] : $data;

        $event = new ReplicationEvent(
            eventId: uniqid('evt_' . $this->regionId . '_', true),
            table: $table,
            primaryKey: $pk,
            operation: $op,
            oldData: $oldData,
            newData: $newData,
            originRegion: $this->regionId,
            vectorClock: $this->vectorClock,
            timestamp: $now,
            logicalClock: $this->logicalClock
        );

        $this->tables[$table][$strPk] = [
            'data' => $newData,
            'vector_clock' => $this->vectorClock,
            'timestamp' => $now,
            'deleted' => $isDelete,
            'last_event' => $event,
        ];

        $this->mutationJournal[] = $event;

        // Queue to all peer outboxes
        foreach ($peerRegions as $peer) {
            if ($peer !== $this->regionId) {
                $this->outbox[$peer][] = $event;
            }
        }

        return $event;
    }

    /**
     * Apply incoming remote replication event.
     */
    public function applyRemoteEvent(
        ReplicationEvent $event,
        ConflictResolver $resolver,
        ConflictResolutionStrategy $strategy = ConflictResolutionStrategy::LAST_WRITE_WINS,
        ?callable $customResolver = null
    ): bool {
        $table = $event->table;
        $strPk = (string) $event->primaryKey;

        // Advance vector clock with incoming event's clock
        $this->vectorClock = $resolver->mergeVectorClocks($this->vectorClock, $event->vectorClock);
        $this->logicalClock = max($this->logicalClock, $event->logicalClock) + 1;

        if (!isset($this->tables[$table][$strPk])) {
            // No local conflict; direct apply
            $isDelete = ($event->operation === 'DELETE');
            $this->tables[$table][$strPk] = [
                'data' => $event->newData,
                'vector_clock' => $event->vectorClock,
                'timestamp' => $event->timestamp,
                'deleted' => $isDelete,
                'last_event' => $event,
            ];
            return true;
        }

        // Row exists locally; detect potential concurrency / conflict
        $existing = $this->tables[$table][$strPk];
        /** @var ReplicationEvent $localEvent */
        $localEvent = $existing['last_event'];

        // If exact same event ID already applied, skip
        if ($localEvent->eventId === $event->eventId) {
            return true;
        }

        // Resolve conflict
        $winner = $resolver->resolve($localEvent, $event, $strategy, $customResolver);

        $this->conflictHistory[] = [
            'local' => $localEvent,
            'remote' => $event,
            'resolved' => $winner,
            'timestamp' => time(),
        ];

        $isDelete = ($winner->operation === 'DELETE');
        $this->tables[$table][$strPk] = [
            'data' => $winner->newData,
            'vector_clock' => $winner->vectorClock,
            'timestamp' => $winner->timestamp,
            'deleted' => $isDelete,
            'last_event' => $winner,
        ];

        return true;
    }

    /**
     * Drain and clear outbox queue for a destination region.
     *
     * @return array<ReplicationEvent>
     */
    public function drainOutboxFor(string $targetRegion): array
    {
        $events = $this->outbox[$targetRegion] ?? [];
        $this->outbox[$targetRegion] = [];
        return $events;
    }

    public function getPendingOutboxCount(): int
    {
        $count = 0;
        foreach ($this->outbox as $queue) {
            $count += count($queue);
        }
        return $count;
    }

    public function getConflictHistory(): array
    {
        return $this->conflictHistory;
    }

    public function getMutationJournal(): array
    {
        return $this->mutationJournal;
    }
}
