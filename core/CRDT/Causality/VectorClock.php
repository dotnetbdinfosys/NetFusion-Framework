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

namespace NetFusion\Core\CRDT\Causality;

/**
 * Vector Clock tracks causal dependency across arbitrary number of distributed nodes.
 */
class VectorClock
{
    /** @var array<string, int> */
    private array $clock;

    public function __construct(array $clock = [])
    {
        $this->clock = [];
        foreach ($clock as $nodeId => $counter) {
            $this->clock[(string)$nodeId] = (int)$counter;
        }
    }

    public function get(string $nodeId): int
    {
        return $this->clock[$nodeId] ?? 0;
    }

    public function set(string $nodeId, int $counter): self
    {
        $this->clock[$nodeId] = max(0, $counter);
        return $this;
    }

    public function increment(string $nodeId): self
    {
        $this->clock[$nodeId] = ($this->clock[$nodeId] ?? 0) + 1;
        return $this;
    }

    /**
     * Merge with another Vector Clock by taking pointwise maximums.
     */
    public function merge(VectorClock $other): self
    {
        foreach ($other->all() as $nodeId => $counter) {
            $this->clock[$nodeId] = max($this->clock[$nodeId] ?? 0, $counter);
        }
        return $this;
    }

    /**
     * Determine if this Vector Clock dominates (strictly happened after) another.
     */
    public function dominates(VectorClock $other): bool
    {
        $strictlyGreater = false;
        $allNodes = array_unique(array_merge(array_keys($this->clock), array_keys($other->all())));

        foreach ($allNodes as $nodeId) {
            $v1 = $this->get($nodeId);
            $v2 = $other->get($nodeId);

            if ($v1 < $v2) {
                return false;
            }
            if ($v1 > $v2) {
                $strictlyGreater = true;
            }
        }

        return $strictlyGreater;
    }

    /**
     * Determine causal relation:
     * - 'EQUAL'
     * - 'BEFORE' ($this happened before $other)
     * - 'AFTER'  ($this happened after $other)
     * - 'CONCURRENT' ($this and $other conflict/branched concurrently)
     */
    public function relationTo(VectorClock $other): string
    {
        $thisGreater  = false;
        $otherGreater = false;
        $allNodes = array_unique(array_merge(array_keys($this->clock), array_keys($other->all())));

        foreach ($allNodes as $nodeId) {
            $v1 = $this->get($nodeId);
            $v2 = $other->get($nodeId);

            if ($v1 > $v2) {
                $thisGreater = true;
            } elseif ($v1 < $v2) {
                $otherGreater = true;
            }
        }

        if ($thisGreater && $otherGreater) {
            return 'CONCURRENT';
        }
        if ($thisGreater) {
            return 'AFTER';
        }
        if ($otherGreater) {
            return 'BEFORE';
        }

        return 'EQUAL';
    }

    public function isConcurrentWith(VectorClock $other): bool
    {
        return $this->relationTo($other) === 'CONCURRENT';
    }

    /**
     * @return array<string, int>
     */
    public function all(): array
    {
        return $this->clock;
    }

    public function toArray(): array
    {
        return $this->clock;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function copy(): self
    {
        return new self($this->clock);
    }
}
