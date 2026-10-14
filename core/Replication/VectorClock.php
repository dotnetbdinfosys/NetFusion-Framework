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

namespace NetFusion\Core\Replication;

/**
 * Class VectorClock
 *
 * Per-node Lamport logical clocks for causal ordering.
 *
 * Each node maintains a counter in the vector.
 * Merge takes component-wise maximum.
 * Causality comparison via happensBefore().
 */
class VectorClock
{
    /** @var array<string, int> nodeId → counter */
    private array $clock = [];

    public function __construct(array $state = [])
    {
        $this->clock = $state;
    }

    // ------------------------------------------------------------------
    // Operations
    // ------------------------------------------------------------------

    /** Increment this node's counter */
    public function increment(string $nodeId): self
    {
        $clone = clone $this;
        $clone->clock[$nodeId] = ($clone->clock[$nodeId] ?? 0) + 1;
        return $clone;
    }

    /** Merge two clocks (component-wise maximum) */
    public function merge(self $other): self
    {
        $clone = clone $this;
        foreach ($other->clock as $node => $count) {
            $clone->clock[$node] = max($clone->clock[$node] ?? 0, $count);
        }
        return $clone;
    }

    // ------------------------------------------------------------------
    // Causality
    // ------------------------------------------------------------------

    /**
     * Returns true if $this happened-before $other.
     * (all components of $this ≤ $other, and at least one is strictly <)
     */
    public function happensBefore(self $other): bool
    {
        $allNodes  = array_unique(array_merge(array_keys($this->clock), array_keys($other->clock)));
        $strictlyLess = false;

        foreach ($allNodes as $node) {
            $a = $this->clock[$node] ?? 0;
            $b = $other->clock[$node] ?? 0;
            if ($a > $b) return false;      // At least one component > other → not happened-before
            if ($a < $b) $strictlyLess = true;
        }

        return $strictlyLess;
    }

    /** Returns true if clocks are concurrent (neither happens-before the other) */
    public function isConcurrent(self $other): bool
    {
        return !$this->happensBefore($other) && !$other->happensBefore($this);
    }

    public function equals(self $other): bool
    {
        $allNodes = array_unique(array_merge(array_keys($this->clock), array_keys($other->clock)));
        foreach ($allNodes as $node) {
            if (($this->clock[$node] ?? 0) !== ($other->clock[$node] ?? 0)) return false;
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function get(string $nodeId): int { return $this->clock[$nodeId] ?? 0; }

    public function toArray(): array { return $this->clock; }

    public static function fromArray(array $state): self { return new self($state); }
}
