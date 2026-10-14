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

use JsonSerializable;

/**
 * Immutable replication journal mutation event across geo-distributed masters.
 */
class ReplicationEvent implements JsonSerializable
{
    /**
     * @param string $eventId Unique mutation identifier
     * @param string $table Database table name
     * @param mixed $primaryKey Primary key value of the row
     * @param string $operation Mutation operation: INSERT, UPDATE, DELETE
     * @param array<string, mixed> $oldData Previous state before mutation
     * @param array<string, mixed> $newData New state after mutation
     * @param string $originRegion Region identifier where mutation occurred
     * @param array<string, int> $vectorClock Vector clock at mutation time
     * @param int $timestamp Physical timestamp in microseconds
     * @param int $logicalClock Lamport logical sequence
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $table,
        public readonly mixed $primaryKey,
        public readonly string $operation,
        public readonly array $oldData,
        public readonly array $newData,
        public readonly string $originRegion,
        public readonly array $vectorClock = [],
        public readonly int $timestamp = 0,
        public readonly int $logicalClock = 0
    ) {
    }

    /**
     * SHA-256 fingerprint of the replication event.
     */
    public function hash(): string
    {
        return hash('sha256', sprintf(
            '%s:%s:%s:%s:%s:%s:%d',
            $this->eventId,
            $this->table,
            (string) $this->primaryKey,
            $this->operation,
            $this->originRegion,
            json_encode($this->newData, JSON_UNESCAPED_SLASHES),
            $this->timestamp
        ));
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'table' => $this->table,
            'primary_key' => $this->primaryKey,
            'operation' => $this->operation,
            'old_data' => $this->oldData,
            'newData' => $this->newData,
            'origin_region' => $this->originRegion,
            'vector_clock' => $this->vectorClock,
            'timestamp' => $this->timestamp,
            'logical_clock' => $this->logicalClock,
            'hash' => $this->hash(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            eventId: (string) ($data['event_id'] ?? uniqid('evt_', true)),
            table: (string) ($data['table'] ?? ''),
            primaryKey: $data['primary_key'] ?? null,
            operation: (string) ($data['operation'] ?? 'UPDATE'),
            oldData: (array) ($data['old_data'] ?? []),
            newData: (array) ($data['new_data'] ?? ($data['newData'] ?? [])),
            originRegion: (string) ($data['origin_region'] ?? 'default'),
            vectorClock: (array) ($data['vector_clock'] ?? []),
            timestamp: (int) ($data['timestamp'] ?? 0),
            logicalClock: (int) ($data['logical_clock'] ?? 0)
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
