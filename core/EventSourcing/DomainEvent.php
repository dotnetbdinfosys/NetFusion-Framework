<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/EventSourcing/DomainEvent.php
| Module    : EventSourcing
| Namespace : NetFusion\Core\EventSourcing
| Purpose   : Base abstract class for immutable Domain Events.
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

use NetFusion\Core\EventSourcing\Contracts\DomainEventInterface;

abstract class DomainEvent implements DomainEventInterface
{
    protected string $eventId;
    protected string $streamId = '';
    protected string $aggregateType = '';
    protected int $version = 0;
    protected array $payload = [];
    protected array $metadata = [];
    protected string $occurredAt;

    public function __construct(array $payload = [], array $metadata = [], ?string $eventId = null, ?string $occurredAt = null)
    {
        $this->eventId = $eventId ?? $this->generateUuid();
        $this->payload = $payload;
        $this->metadata = $metadata;
        $this->occurredAt = $occurredAt ?? date('Y-m-d H:i:s.u');
    }

    public static function create(array $payload = [], array $metadata = []): static
    {
        return new static($payload, $metadata);
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getStreamId(): string
    {
        return $this->streamId;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function withVersion(int $version): static
    {
        $clone = clone $this;
        $clone->version = $version;
        return $clone;
    }

    public function withStreamId(string $streamId): static
    {
        $clone = clone $this;
        $clone->streamId = $streamId;
        return $clone;
    }

    public function withAggregateType(string $aggregateType): static
    {
        $clone = clone $this;
        $clone->aggregateType = $aggregateType;
        return $clone;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getOccurredAt(): string
    {
        return $this->occurredAt;
    }

    public function toArray(): array
    {
        return [
            'event_id'       => $this->eventId,
            'stream_id'      => $this->streamId,
            'aggregate_type' => $this->aggregateType,
            'version'        => $this->version,
            'event_name'     => static::class,
            'payload'        => $this->payload,
            'metadata'       => $this->metadata,
            'occurred_at'    => $this->occurredAt,
        ];
    }

    protected function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}