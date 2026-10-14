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

namespace NetFusion\Core\DistributedDb\Raft;

use JsonSerializable;

/**
 * Immutable Raft replicated state machine log entry.
 */
class LogEntry implements JsonSerializable
{
    public function __construct(
        public readonly int $term,
        public readonly int $index,
        public readonly string $command,
        public readonly mixed $payload = null,
        public readonly int $timestamp = 0,
        public readonly string $clientRequestId = ''
    ) {
    }

    /**
     * Compute SHA-256 fingerprint for entry integrity.
     */
    public function hash(): string
    {
        return hash('sha256', sprintf(
            '%d:%d:%s:%s:%s',
            $this->term,
            $this->index,
            $this->command,
            json_encode($this->payload, JSON_UNESCAPED_SLASHES),
            $this->clientRequestId
        ));
    }

    /**
     * Convert log entry to array representation.
     */
    public function toArray(): array
    {
        return [
            'term' => $this->term,
            'index' => $this->index,
            'command' => $this->command,
            'payload' => $this->payload,
            'timestamp' => $this->timestamp,
            'client_request_id' => $this->clientRequestId,
            'hash' => $this->hash(),
        ];
    }

    /**
     * Restore log entry from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            term: (int) ($data['term'] ?? 0),
            index: (int) ($data['index'] ?? 0),
            command: (string) ($data['command'] ?? ''),
            payload: $data['payload'] ?? null,
            timestamp: (int) ($data['timestamp'] ?? 0),
            clientRequestId: (string) ($data['client_request_id'] ?? '')
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
