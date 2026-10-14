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

namespace NetFusion\Core\CRDT\Sync;

use NetFusion\Core\CRDT\Enums\SyncMode;

/**
 * Wire-format sync envelope for peer-to-peer or client-server replication.
 */
class SyncMessage
{
    private string $senderId;
    private string $documentId;
    private SyncMode $mode;
    private array $vectorClock;
    private array $payload;
    private float $timestamp;

    public function __construct(
        string $senderId,
        string $documentId,
        SyncMode $mode,
        array $vectorClock,
        array $payload,
        ?float $timestamp = null
    ) {
        $this->senderId    = $senderId;
        $this->documentId  = $documentId;
        $this->mode        = $mode;
        $this->vectorClock = $vectorClock;
        $this->payload     = $payload;
        $this->timestamp   = $timestamp ?? microtime(true);
    }

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getMode(): SyncMode
    {
        return $this->mode;
    }

    public function getVectorClock(): array
    {
        return $this->vectorClock;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'sender_id'    => $this->senderId,
            'document_id'  => $this->documentId,
            'mode'         => $this->mode->value,
            'vector_clock' => $this->vectorClock,
            'payload'      => $this->payload,
            'timestamp'    => $this->timestamp,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['sender_id'] ?? ''),
            (string)($data['document_id'] ?? ''),
            SyncMode::from($data['mode']),
            (array)($data['vector_clock'] ?? []),
            (array)($data['payload'] ?? []),
            (float)($data['timestamp'] ?? microtime(true))
        );
    }
}
