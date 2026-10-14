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

namespace NetFusion\Core\Data\Replication;

use JsonSerializable;
use NetFusion\Core\Data\Crdt\VectorClock;

class ReplicationEvent implements JsonSerializable
{
    public const OP_INSERT = 'INSERT';
    public const OP_UPDATE = 'UPDATE';
    public const OP_DELETE = 'DELETE';
    public const OP_CRDT_MERGE = 'CRDT_MERGE';

    public function __construct(
        public readonly string $id,
        public readonly string $originRegion,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $operation,
        public readonly array $payload,
        public readonly VectorClock $vectorClock,
        public readonly float $timestamp,
        public string $status = 'pending'
    ) {
    }

    public static function create(
        string $originRegion,
        string $entityType,
        string $entityId,
        string $operation,
        array $payload,
        ?VectorClock $vectorClock = null,
        ?float $timestamp = null
    ): self {
        $clock = $vectorClock ?? (new VectorClock())->increment($originRegion);
        $id = bin2hex(random_bytes(16));
        $ts = $timestamp ?? microtime(true);

        return new self(
            id: $id,
            originRegion: $originRegion,
            entityType: $entityType,
            entityId: $entityId,
            operation: strtoupper($operation),
            payload: $payload,
            vectorClock: $clock,
            timestamp: $ts,
            status: 'pending'
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'origin_region' => $this->originRegion,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'operation' => $this->operation,
            'payload' => $this->payload,
            'vector_clock' => $this->vectorClock->toArray(),
            'timestamp' => $this->timestamp,
            'status' => $this->status,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)$data['id'],
            originRegion: (string)$data['origin_region'],
            entityType: (string)$data['entity_type'],
            entityId: (string)$data['entity_id'],
            operation: (string)$data['operation'],
            payload: (array)($data['payload'] ?? []),
            vectorClock: new VectorClock((array)($data['vector_clock'] ?? [])),
            timestamp: (float)($data['timestamp'] ?? microtime(true)),
            status: (string)($data['status'] ?? 'pending')
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
