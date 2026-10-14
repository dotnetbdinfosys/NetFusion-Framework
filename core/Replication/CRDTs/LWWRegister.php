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

namespace NetFusion\Core\Replication\CRDTs;

/**
 * Class LWWRegister (Last-Write-Wins Register CRDT)
 *
 * A single-value register where concurrent writes resolve by
 * using the value with the highest timestamp.
 *
 * Properties:
 *   - Deterministic conflict resolution (timestamp-based)
 *   - On tie: higher regionId wins (lexicographic)
 */
class LWWRegister
{
    private mixed  $value;
    private float  $timestamp;
    private string $regionId;

    public function __construct(
        mixed  $value     = null,
        float  $timestamp = 0.0,
        string $regionId  = ''
    ) {
        $this->value     = $value;
        $this->timestamp = $timestamp;
        $this->regionId  = $regionId;
    }

    // ------------------------------------------------------------------
    // Operations
    // ------------------------------------------------------------------

    public function set(mixed $value, string $regionId, ?float $timestamp = null): self
    {
        $clone = clone $this;
        $clone->value     = $value;
        $clone->regionId  = $regionId;
        $clone->timestamp = $timestamp ?? microtime(true);
        return $clone;
    }

    // ------------------------------------------------------------------
    // Value
    // ------------------------------------------------------------------

    public function get(): mixed { return $this->value; }

    public function getTimestamp(): float { return $this->timestamp; }

    public function getRegionId(): string { return $this->regionId; }

    // ------------------------------------------------------------------
    // Merge (higher timestamp wins; on tie, higher regionId wins)
    // ------------------------------------------------------------------

    public function merge(self $other): self
    {
        if ($other->timestamp > $this->timestamp) {
            return clone $other;
        }
        if ($other->timestamp === $this->timestamp && $other->regionId > $this->regionId) {
            return clone $other;
        }
        return clone $this;
    }

    // ------------------------------------------------------------------
    // Serialization
    // ------------------------------------------------------------------

    public function toArray(): array
    {
        return [
            'value'     => $this->value,
            'timestamp' => $this->timestamp,
            'region_id' => $this->regionId,
        ];
    }

    public static function fromArray(array $state): self
    {
        return new self($state['value'], $state['timestamp'] ?? 0.0, $state['region_id'] ?? '');
    }
}
