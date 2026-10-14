<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Consul\Gossip\Messages;

use NetFusion\Core\Consul\Enums\NodeStatus;

/**
 * Represents a SWIM gossip packet exchanged between cluster nodes.
 */
class GossipMessage
{
    public const TYPE_PING      = 'ping';
    public const TYPE_ACK       = 'ack';
    public const TYPE_PING_REQ  = 'ping_req';
    public const TYPE_JOIN      = 'join';
    public const TYPE_LEAVE     = 'leave';
    public const TYPE_SUSPECT   = 'suspect';
    public const TYPE_ALIVE     = 'alive';
    public const TYPE_DEAD      = 'dead';

    protected string $type;
    protected string $sourceNodeId;
    protected ?string $targetNodeId;
    protected int $sequenceNumber;
    protected int $incarnation;
    /** @var array<string, mixed> */
    protected array $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $type,
        string $sourceNodeId,
        ?string $targetNodeId = null,
        int $sequenceNumber = 0,
        int $incarnation = 0,
        array $payload = []
    ) {
        $this->type = $type;
        $this->sourceNodeId = $sourceNodeId;
        $this->targetNodeId = $targetNodeId;
        $this->sequenceNumber = $sequenceNumber > 0 ? $sequenceNumber : random_int(1, 1000000);
        $this->incarnation = $incarnation;
        $this->payload = $payload;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSourceNodeId(): string
    {
        return $this->sourceNodeId;
    }

    public function getTargetNodeId(): ?string
    {
        return $this->targetNodeId;
    }

    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }

    public function getIncarnation(): int
    {
        return $this->incarnation;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getPayloadValue(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function serialize(): string
    {
        $data = [
            'type' => $this->type,
            'source' => $this->sourceNodeId,
            'target' => $this->targetNodeId,
            'seq' => $this->sequenceNumber,
            'incarnation' => $this->incarnation,
            'payload' => $this->payload,
        ];

        return (string)json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    public static function deserialize(string $json): ?self
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['type'], $data['source'])) {
            return null;
        }

        return new self(
            (string)$data['type'],
            (string)$data['source'],
            isset($data['target']) ? (string)$data['target'] : null,
            (int)($data['seq'] ?? 0),
            (int)($data['incarnation'] ?? 0),
            (array)($data['payload'] ?? [])
        );
    }
}
