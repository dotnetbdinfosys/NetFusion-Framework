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
 * Class PNCounter (Positive-Negative Counter CRDT)
 *
 * Supports both increment and decrement by composing two GCounters:
 *   value = P.value() − N.value()
 *
 * Properties:
 *   - Supports decrement (unlike GCounter)
 *   - Merge: merge P and N GCounters independently
 */
class PNCounter
{
    private GCounter $positive;
    private GCounter $negative;

    public function __construct(?GCounter $pos = null, ?GCounter $neg = null)
    {
        $this->positive = $pos ?? new GCounter();
        $this->negative = $neg ?? new GCounter();
    }

    // ------------------------------------------------------------------
    // Operations
    // ------------------------------------------------------------------

    public function increment(string $regionId, int $by = 1): self
    {
        $clone = clone $this;
        $clone->positive = $clone->positive->increment($regionId, $by);
        return $clone;
    }

    public function decrement(string $regionId, int $by = 1): self
    {
        $clone = clone $this;
        $clone->negative = $clone->negative->increment($regionId, $by);
        return $clone;
    }

    // ------------------------------------------------------------------
    // Value
    // ------------------------------------------------------------------

    public function value(): int
    {
        return $this->positive->value() - $this->negative->value();
    }

    // ------------------------------------------------------------------
    // Merge
    // ------------------------------------------------------------------

    public function merge(self $other): self
    {
        $clone = clone $this;
        $clone->positive = $clone->positive->merge($other->positive);
        $clone->negative = $clone->negative->merge($other->negative);
        return $clone;
    }

    // ------------------------------------------------------------------
    // Serialization
    // ------------------------------------------------------------------

    public function toArray(): array
    {
        return [
            'p' => $this->positive->toArray(),
            'n' => $this->negative->toArray(),
        ];
    }

    public static function fromArray(array $state): self
    {
        return new self(
            GCounter::fromArray($state['p'] ?? []),
            GCounter::fromArray($state['n'] ?? [])
        );
    }
}
