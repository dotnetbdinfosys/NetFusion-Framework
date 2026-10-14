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

use NetFusion\Core\Replication\VectorClock;

/**
 * Class MVRegister (Multi-Value Register CRDT)
 *
 * Preserves all concurrent write values (unlike LWWRegister which picks one).
 * Each write is tagged with a VectorClock. On merge, values that are
 * dominated (happened-before another) are discarded; concurrent values
 * are all retained.
 *
 * Properties:
 *   - Concurrent writes result in multiple values (conflict exposure)
 *   - get() returns the set of concurrent values
 *   - set() discards all existing values, starts fresh
 */
class MVRegister
{
    /** @var array[] [value, clock] pairs */
    private array $entries = [];

    // ------------------------------------------------------------------
    // Operations
    // ------------------------------------------------------------------

    public function set(mixed $value, string $nodeId): self
    {
        $clone = clone $this;
        // Build new clock: merge all existing clocks, increment nodeId
        $merged = new VectorClock();
        foreach ($clone->entries as $e) {
            $merged = $merged->merge($e['clock']);
        }
        $merged = $merged->increment($nodeId);

        $clone->entries = [['value' => $value, 'clock' => $merged]];
        return $clone;
    }

    // ------------------------------------------------------------------
    // Queries
    // ------------------------------------------------------------------

    /**
     * Get all values (may be more than one if concurrent writes).
     * @return mixed[]
     */
    public function get(): array
    {
        return array_column($this->entries, 'value');
    }

    public function isConcurrent(): bool
    {
        return count($this->entries) > 1;
    }

    // ------------------------------------------------------------------
    // Merge
    // ------------------------------------------------------------------

    public function merge(self $other): self
    {
        $clone = clone $this;
        $all   = array_merge($clone->entries, $other->entries);

        // Remove entries whose clock happened-before another entry's clock
        $maximal = [];
        foreach ($all as $i => $entry) {
            $dominated = false;
            foreach ($all as $j => $other_entry) {
                if ($i === $j) continue;
                if ($entry['clock']->happensBefore($other_entry['clock'])) {
                    $dominated = true;
                    break;
                }
            }
            if (!$dominated) {
                $maximal[] = $entry;
            }
        }

        // Deduplicate (same value + equal clock)
        $seen = [];
        $deduped = [];
        foreach ($maximal as $e) {
            $key = md5(serialize($e['value']) . serialize($e['clock']->toArray()));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $e;
            }
        }

        $clone->entries = $deduped;
        return $clone;
    }

    // ------------------------------------------------------------------
    // Serialization
    // ------------------------------------------------------------------

    public function toArray(): array
    {
        return array_map(fn($e) => [
            'value' => $e['value'],
            'clock' => $e['clock']->toArray(),
        ], $this->entries);
    }

    public static function fromArray(array $state): self
    {
        $reg = new self();
        $reg->entries = array_map(fn($e) => [
            'value' => $e['value'],
            'clock' => VectorClock::fromArray($e['clock']),
        ], $state);
        return $reg;
    }
}
