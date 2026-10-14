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

namespace NetFusion\Core\Data\Crdt;

use JsonSerializable;

class VectorClock implements JsonSerializable
{
    /**
     * @param array<string, int> $clock
     */
    public function __construct(
        protected array $clock = []
    ) {
    }

    public function increment(string $nodeId): self
    {
        $this->clock[$nodeId] = ($this->clock[$nodeId] ?? 0) + 1;
        return $this;
    }

    public function get(string $nodeId): int
    {
        return $this->clock[$nodeId] ?? 0;
    }

    public function set(string $nodeId, int $val): self
    {
        $this->clock[$nodeId] = max(0, $val);
        return $this;
    }

    public function all(): array
    {
        return $this->clock;
    }

    /**
     * Merge with another vector clock taking the component-wise maximum.
     */
    public function merge(VectorClock $other): self
    {
        $merged = $this->clock;
        foreach ($other->all() as $nodeId => $counter) {
            $merged[$nodeId] = max($merged[$nodeId] ?? 0, $counter);
        }
        $this->clock = $merged;
        return $this;
    }

    /**
     * Test if this clock strictly precedes (happens-before) another clock: $V_A < V_B.
     * True if for all i, A[i] <= B[i] and there exists at least one j where A[j] < B[j].
     */
    public function happensBefore(VectorClock $other): bool
    {
        $allKeys = array_unique(array_merge(array_keys($this->clock), array_keys($other->all())));
        $hasStrictlyLess = false;

        foreach ($allKeys as $key) {
            $a = $this->get($key);
            $b = $other->get($key);

            if ($a > $b) {
                return false;
            }
            if ($a < $b) {
                $hasStrictlyLess = true;
            }
        }

        return $hasStrictlyLess;
    }

    /**
     * Test if two vector clocks are concurrent (causally unrelated / conflict).
     */
    public function isConcurrent(VectorClock $other): bool
    {
        return !$this->isEqualTo($other) && !$this->happensBefore($other) && !$other->happensBefore($this);
    }

    /**
     * Test if two vector clocks are identical.
     */
    public function isEqualTo(VectorClock $other): bool
    {
        $allKeys = array_unique(array_merge(array_keys($this->clock), array_keys($other->all())));
        foreach ($allKeys as $key) {
            if ($this->get($key) !== $other->get($key)) {
                return false;
            }
        }
        return true;
    }

    public function copy(): self
    {
        return new self($this->clock);
    }

    public function toArray(): array
    {
        return $this->clock;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return json_encode($this->clock) ?: '{}';
    }
}
