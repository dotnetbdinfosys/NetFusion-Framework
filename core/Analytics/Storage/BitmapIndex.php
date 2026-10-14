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

namespace NetFusion\Core\Analytics\Storage;

/**
 * High-performance compressed bitmap index for dimension values.
 * Stores sorted row IDs matching specific dimension values, enabling lightning-fast bitwise AND / OR filtering.
 */
class BitmapIndex
{
    /** @var array<int, bool> Set of row IDs [rowId => true] */
    protected array $bits = [];

    /**
     * @param array<int> $rowIds
     */
    public function __construct(array $rowIds = [])
    {
        foreach ($rowIds as $id) {
            $this->bits[(int)$id] = true;
        }
    }

    public function set(int $rowId): self
    {
        $this->bits[$rowId] = true;
        return $this;
    }

    public function has(int $rowId): bool
    {
        return isset($this->bits[$rowId]);
    }

    /**
     * Perform Bitwise AND (Intersection) with another bitmap.
     */
    public function and(self $other): self
    {
        $result = new self();
        foreach ($this->bits as $id => $_) {
            if ($other->has($id)) {
                $result->set($id);
            }
        }
        return $result;
    }

    /**
     * Perform Bitwise OR (Union) with another bitmap.
     */
    public function or(self $other): self
    {
        $result = new self();
        foreach ($this->bits as $id => $_) {
            $result->set($id);
        }
        foreach ($other->bits as $id => $_) {
            $result->set($id);
        }
        return $result;
    }

    /**
     * Return all matching row IDs in ascending order.
     *
     * @return array<int>
     */
    public function getRowIds(): array
    {
        $keys = array_keys($this->bits);
        sort($keys, SORT_NUMERIC);
        return $keys;
    }

    public function count(): int
    {
        return count($this->bits);
    }
}
