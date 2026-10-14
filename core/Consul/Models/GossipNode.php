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

namespace NetFusion\Core\Consul\Models;

use NetFusion\Core\Consul\Enums\NodeStatus;

/**
 * Represents a member node in the SWIM gossip mesh cluster.
 */
class GossipNode
{
    protected string $id;
    protected string $name;
    protected string $address;
    protected int $port;
    protected NodeStatus $status;
    protected int $incarnation;
    protected float $lastSeen;
    protected ?float $suspectSince = null;
    /** @var array<string, mixed> */
    protected array $metadata;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $id,
        string $name,
        string $address,
        int $port = 8301,
        NodeStatus $status = NodeStatus::ALIVE,
        int $incarnation = 0,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->address = $address;
        $this->port = $port;
        $this->status = $status;
        $this->incarnation = $incarnation;
        $this->lastSeen = microtime(true);
        $this->metadata = $metadata;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getStatus(): NodeStatus
    {
        return $this->status;
    }

    public function setStatus(NodeStatus $status): self
    {
        if ($status === NodeStatus::SUSPECT && $this->status !== NodeStatus::SUSPECT) {
            $this->suspectSince = microtime(true);
        } elseif ($status !== NodeStatus::SUSPECT) {
            $this->suspectSince = null;
        }
        $this->status = $status;
        return $this;
    }

    public function getIncarnation(): int
    {
        return $this->incarnation;
    }

    public function setIncarnation(int $incarnation): self
    {
        $this->incarnation = $incarnation;
        return $this;
    }

    public function incrementIncarnation(): int
    {
        $this->incarnation++;
        return $this->incarnation;
    }

    public function getLastSeen(): float
    {
        return $this->lastSeen;
    }

    public function touch(): self
    {
        $this->lastSeen = microtime(true);
        return $this;
    }

    public function getSuspectSince(): ?float
    {
        return $this->suspectSince;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isAlive(): bool
    {
        return $this->status === NodeStatus::ALIVE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'port' => $this->port,
            'status' => $this->status->value,
            'incarnation' => $this->incarnation,
            'lastSeen' => $this->lastSeen,
            'suspectSince' => $this->suspectSince,
            'metadata' => $this->metadata,
        ];
    }
}
