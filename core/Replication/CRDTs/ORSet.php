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
 * Class ORSet (Observed-Remove Set CRDT)
 *
 * An add-wins set that supports both add and remove operations.
 *
 * Design:
 *   Each element is tagged with a unique ID when added.
 *   Removal removes all current tags for that element.
 *   Merge: element present if any tag exists in union that
 *   is not in the tombstone set.
 *
 * Properties:
 *   - Add-wins on concurrent add + remove
 *   - Commutative, Associative, Idempotent
 */
class ORSet
{
    /** @var array<string, string[]> element → [tag, ...] */
    private array $elements = [];

    /** @var array<string, string[]> element → [removed_tag, ...] (tombstones) */
    private array $tombstones = [];

    // ------------------------------------------------------------------
    // Operations
    // ------------------------------------------------------------------

    public function add(mixed $element): self
    {
        $key   = $this->key($element);
        $tag   = uniqid('tag_', true);
        $clone = clone $this;
        $clone->elements[$key]   ??= [];
        $clone->elements[$key][] = $tag;
        return $clone;
    }

    public function remove(mixed $element): self
    {
        $key   = $this->key($element);
        $clone = clone $this;

        if (isset($clone->elements[$key])) {
            // Tombstone all current tags
            $clone->tombstones[$key]   ??= [];
            $clone->tombstones[$key]    = array_unique(
                array_merge($clone->tombstones[$key], $clone->elements[$key])
            );
            // Remove from active
            unset($clone->elements[$key]);
        }

        return $clone;
    }

    // ------------------------------------------------------------------
    // Queries
    // ------------------------------------------------------------------

    public function contains(mixed $element): bool
    {
        $key = $this->key($element);
        $active = array_diff($this->elements[$key] ?? [], $this->tombstones[$key] ?? []);
        return !empty($active);
    }

    /** @return string[] All currently active elements */
    public function values(): array
    {
        $result = [];
        foreach ($this->elements as $key => $tags) {
            $active = array_diff($tags, $this->tombstones[$key] ?? []);
            if (!empty($active)) {
                $result[] = $key;
            }
        }
        return $result;
    }

    public function count(): int { return count($this->values()); }

    // ------------------------------------------------------------------
    // Merge (union of elements, union of tombstones, then filter)
    // ------------------------------------------------------------------

    public function merge(self $other): self
    {
        $clone = clone $this;

        foreach ($other->elements as $key => $tags) {
            $clone->elements[$key] = array_unique(
                array_merge($clone->elements[$key] ?? [], $tags)
            );
        }
        foreach ($other->tombstones as $key => $tags) {
            $clone->tombstones[$key] = array_unique(
                array_merge($clone->tombstones[$key] ?? [], $tags)
            );
        }

        return $clone;
    }

    // ------------------------------------------------------------------
    // Serialization
    // ------------------------------------------------------------------

    public function toArray(): array
    {
        return [
            'elements'   => $this->elements,
            'tombstones' => $this->tombstones,
        ];
    }

    public static function fromArray(array $state): self
    {
        $set             = new self();
        $set->elements   = $state['elements']   ?? [];
        $set->tombstones = $state['tombstones']  ?? [];
        return $set;
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function key(mixed $element): string
    {
        if (is_string($element) || is_int($element) || is_float($element)) {
            return (string) $element;
        }
        return md5(serialize($element));
    }
}
