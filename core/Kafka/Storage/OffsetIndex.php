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

namespace NetFusion\Core\Kafka\Storage;

/**
 * Sparse memory index mapping logical record offsets to physical position pointers.
 */
class OffsetIndex
{
    /** @var array<int, int> Map of logical offset to position index */
    protected array $index = [];

    /**
     * Append an offset-position pair to the index.
     */
    public function append(int $offset, int $position): void
    {
        $this->index[$offset] = $position;
    }

    /**
     * Binary search to find the largest indexed offset less than or equal to target offset.
     */
    public function lookup(int $targetOffset): ?int
    {
        if (empty($this->index)) {
            return null;
        }

        $offsets = array_keys($this->index);
        $low = 0;
        $high = count($offsets) - 1;
        $result = null;

        while ($low <= $high) {
            $mid = (int) (($low + $high) / 2);
            $currOffset = $offsets[$mid];

            if ($currOffset === $targetOffset) {
                return $this->index[$currOffset];
            }

            if ($currOffset < $targetOffset) {
                $result = $this->index[$currOffset];
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $result;
    }

    /**
     * Truncate the index removing entries at or above the given offset.
     */
    public function truncateTo(int $targetOffset): void
    {
        foreach (array_keys($this->index) as $offset) {
            if ($offset >= $targetOffset) {
                unset($this->index[$offset]);
            }
        }
    }

    /**
     * Get count of index entries.
     */
    public function count(): int
    {
        return count($this->index);
    }

    /**
     * Clear all index entries.
     */
    public function clear(): void
    {
        $this->index = [];
    }
}
