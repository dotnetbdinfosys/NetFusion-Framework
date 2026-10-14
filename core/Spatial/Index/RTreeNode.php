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

namespace NetFusion\Core\Spatial\Index;

use NetFusion\Core\Spatial\Geometry\BoundingBox;

/**
 * R-Tree Node (Internal node or Leaf node).
 */
class RTreeNode
{
    public bool $isLeaf;
    public ?BoundingBox $bbox = null;
    /** @var array<int, array{id: string|int, bbox: BoundingBox, data: mixed}> */
    public array $entries = [];
    /** @var array<int, RTreeNode> */
    public array $children = [];
    public ?RTreeNode $parent = null;

    public function __construct(bool $isLeaf = true)
    {
        $this->isLeaf = $isLeaf;
    }

    public function recalculateBBox(): void
    {
        if ($this->isLeaf) {
            if (empty($this->entries)) {
                $this->bbox = null;
                return;
            }
            $box = $this->entries[0]['bbox'];
            for ($i = 1; $i < count($this->entries); $i++) {
                $box = $box->expand($this->entries[$i]['bbox']);
            }
            $this->bbox = $box;
        } else {
            if (empty($this->children)) {
                $this->bbox = null;
                return;
            }
            $box = $this->children[0]->bbox;
            for ($i = 1; $i < count($this->children); $i++) {
                if ($this->children[$i]->bbox !== null) {
                    $box = $box !== null ? $box->expand($this->children[$i]->bbox) : $this->children[$i]->bbox;
                }
            }
            $this->bbox = $box;
        }
    }

    public function addEntry(string|int $id, BoundingBox $bbox, mixed $data): void
    {
        $this->entries[] = ['id' => $id, 'bbox' => $bbox, 'data' => $data];
        $this->bbox = $this->bbox !== null ? $this->bbox->expand($bbox) : $bbox;
    }

    public function addChild(self $child): void
    {
        $child->parent = $this;
        $this->children[] = $child;
        if ($child->bbox !== null) {
            $this->bbox = $this->bbox !== null ? $this->bbox->expand($child->bbox) : $child->bbox;
        }
    }

    public function count(): int
    {
        return $this->isLeaf ? count($this->entries) : count($this->children);
    }
}
