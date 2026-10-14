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
 * Class GCounter (Grow-Only Counter CRDT)
 *
 * A monotonically increasing distributed counter.
 * Each node/region maintains its own counter shard.
 * The global value is the sum of all shards.
 *
 * Properties:
 *   - Commutative, Associative, Idempotent (CRDTs)
 *   - Monotonically increasing only
 *   - Merge: component-wise maximum
 */
class GCounter
{
    /** @var array<string, int> regionId → count */
    private array $counters = [];

    public function __construct(array $state = [])
    {
        $this->counters = $state;
    }

    // ------------------------------------------------------------------
    // Operations
    // ------------------------------------------------------------------

    public function increment(string $regionId, int $by = 1): self
    {
        if ($by < 0) throw new \InvalidArgumentException("GCounter can only increment (got {$by})");
        $clone = clone $this;
        $clone->counters[$regionId] = ($clone->counters[$regionId] ?? 0) + $by;
        return $clone;
    }

    // ------------------------------------------------------------------
    // Value
    // ------------------------------------------------------------------

    /** Sum of all region counters */
    public function value(): int
    {
        return (int) array_sum($this->counters);
    }

    public function get(string $regionId): int
    {
        return $this->counters[$regionId] ?? 0;
    }

    // ------------------------------------------------------------------
    // Merge (component-wise maximum)
    // ------------------------------------------------------------------

    public function merge(self $other): self
    {
        $clone = clone $this;
        foreach ($other->counters as $regionId => $count) {
            $clone->counters[$regionId] = max($clone->counters[$regionId] ?? 0, $count);
        }
        return $clone;
    }

    // ------------------------------------------------------------------
    // Serialization
    // ------------------------------------------------------------------

    public function toArray(): array { return $this->counters; }

    public static function fromArray(array $state): self
    {
        return new self($state);
    }

    public function compare(self $other): bool
    {
        foreach ($this->counters as $r => $v) {
            if ($v > ($other->counters[$r] ?? 0)) return false;
        }
        return true;
    }
}
