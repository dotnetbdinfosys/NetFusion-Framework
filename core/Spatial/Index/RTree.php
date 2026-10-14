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

use NetFusion\Core\Spatial\Geodesic\DistanceCalculator;
use NetFusion\Core\Spatial\Geometry\BoundingBox;
use NetFusion\Core\Spatial\Geometry\Point;

/**
 * 2D R-Tree Spatial Index.
 * Supports bounding box intersection queries and K-Nearest Neighbors (KNN) search.
 */
class RTree
{
    protected RTreeNode $root;
    protected int $maxEntries;
    protected int $minEntries;
    protected int $size = 0;

    public function __construct(int $maxEntries = 16, int $minEntries = 4)
    {
        $this->maxEntries = max(4, $maxEntries);
        $this->minEntries = max(2, min($minEntries, (int) floor($this->maxEntries / 2)));
        $this->root = new RTreeNode(isLeaf: true);
    }

    public function count(): int
    {
        return $this->size;
    }

    public function getRoot(): RTreeNode
    {
        return $this->root;
    }

    /**
     * Insert a Point into the spatial index.
     */
    public function insertPoint(Point $point, mixed $data, string|int $id): self
    {
        $box = new BoundingBox($point->getLat(), $point->getLng(), $point->getLat(), $point->getLng());
        return $this->insert($box, $data, $id);
    }

    /**
     * Insert a BoundingBox entry into the spatial index.
     */
    public function insert(BoundingBox $bbox, mixed $data, string|int $id): self
    {
        $leaf = $this->chooseLeaf($this->root, $bbox);
        $leaf->addEntry($id, $bbox, $data);
        $this->size++;

        if ($leaf->count() > $this->maxEntries) {
            $this->split($leaf);
        } else {
            $this->adjustBoundsUpwards($leaf);
        }

        return $this;
    }

    /**
     * Query for all entries whose bounding boxes intersect the query box.
     *
     * @return array<int, array{id: string|int, bbox: BoundingBox, data: mixed}>
     */
    public function search(BoundingBox $queryBox): array
    {
        $results = [];
        $this->searchNode($this->root, $queryBox, $results);
        return $results;
    }

    protected function searchNode(RTreeNode $node, BoundingBox $queryBox, array &$results): void
    {
        if ($node->bbox === null || !$node->bbox->intersects($queryBox)) {
            return;
        }

        if ($node->isLeaf) {
            foreach ($node->entries as $entry) {
                if ($entry['bbox']->intersects($queryBox)) {
                    $results[] = $entry;
                }
            }
        } else {
            foreach ($node->children as $child) {
                $this->searchNode($child, $queryBox, $results);
            }
        }
    }

    /**
     * K-Nearest Neighbors (KNN) search finding the $k closest entries to a target Point.
     *
     * @return array<int, array{id: string|int, bbox: BoundingBox, data: mixed, distanceMeters: float}>
     */
    public function knn(Point $target, int $k = 5): array
    {
        if ($this->size === 0 || $k <= 0) {
            return [];
        }

        // Priority queue items: ['dist' => float, 'type' => 'node'|'entry', 'item' => mixed]
        $queue = [];
        $push = function (float $dist, string $type, mixed $item) use (&$queue) {
            $queue[] = ['dist' => $dist, 'type' => $type, 'item' => $item];
        };

        if ($this->root->bbox !== null) {
            $push($this->pointToBoxDistance($target, $this->root->bbox), 'node', $this->root);
        }

        $results = [];

        while (!empty($queue) && count($results) < $k) {
            // Sort queue ascending by distance (simulating min-heap priority queue)
            usort($queue, fn($a, $b) => $a['dist'] <=> $b['dist']);
            $current = array_shift($queue);

            if ($current['type'] === 'entry') {
                $entry = $current['item'];
                $entry['distanceMeters'] = $current['dist'];
                $results[] = $entry;
            } else {
                /** @var RTreeNode $node */
                $node = $current['item'];
                if ($node->isLeaf) {
                    foreach ($node->entries as $entry) {
                        $dist = $this->pointToBoxDistance($target, $entry['bbox']);
                        $push($dist, 'entry', $entry);
                    }
                } else {
                    foreach ($node->children as $child) {
                        if ($child->bbox !== null) {
                            $dist = $this->pointToBoxDistance($target, $child->bbox);
                            $push($dist, 'node', $child);
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Delete an entry by ID.
     */
    public function remove(string|int $id): bool
    {
        $removed = $this->removeEntryFromNode($this->root, $id);
        if ($removed) {
            $this->size--;
        }
        return $removed;
    }

    protected function removeEntryFromNode(RTreeNode $node, string|int $id): bool
    {
        if ($node->isLeaf) {
            foreach ($node->entries as $idx => $entry) {
                if ($entry['id'] === $id) {
                    array_splice($node->entries, $idx, 1);
                    $node->recalculateBBox();
                    $this->adjustBoundsUpwards($node);
                    return true;
                }
            }
            return false;
        }

        foreach ($node->children as $child) {
            if ($this->removeEntryFromNode($child, $id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Select the best leaf node to insert the new bounding box (least area enlargement heuristic).
     */
    protected function chooseLeaf(RTreeNode $node, BoundingBox $bbox): RTreeNode
    {
        if ($node->isLeaf) {
            return $node;
        }

        $bestChild = $node->children[0];
        $bestEnlargement = PHP_FLOAT_MAX;

        foreach ($node->children as $child) {
            $curArea = $child->bbox !== null ? $child->bbox->getArea() : 0.0;
            $newBox = $child->bbox !== null ? $child->bbox->expand($bbox) : $bbox;
            $enlargement = $newBox->getArea() - $curArea;

            if ($enlargement < $bestEnlargement) {
                $bestEnlargement = $enlargement;
                $bestChild = $child;
            }
        }

        return $this->chooseLeaf($bestChild, $bbox);
    }

    /**
     * Split node when maximum capacity is exceeded (Quadratic-style split).
     */
    protected function split(RTreeNode $node): void
    {
        $newNode = new RTreeNode(isLeaf: $node->isLeaf);

        if ($node->isLeaf) {
            $allEntries = $node->entries;
            $mid = (int) ceil(count($allEntries) / 2);
            $node->entries = array_slice($allEntries, 0, $mid);
            $newNode->entries = array_slice($allEntries, $mid);
        } else {
            $allChildren = $node->children;
            $mid = (int) ceil(count($allChildren) / 2);
            $node->children = array_slice($allChildren, 0, $mid);
            $newNode->children = array_slice($allChildren, $mid);
            foreach ($newNode->children as $c) {
                $c->parent = $newNode;
            }
        }

        $node->recalculateBBox();
        $newNode->recalculateBBox();

        // If splitting root, create new root
        if ($node->parent === null) {
            $newRoot = new RTreeNode(isLeaf: false);
            $newRoot->addChild($node);
            $newRoot->addChild($newNode);
            $this->root = $newRoot;
            $newRoot->recalculateBBox();
        } else {
            $parent = $node->parent;
            $parent->addChild($newNode);
            $parent->recalculateBBox();

            if ($parent->count() > $this->maxEntries) {
                $this->split($parent);
            } else {
                $this->adjustBoundsUpwards($parent);
            }
        }
    }

    protected function adjustBoundsUpwards(RTreeNode $node): void
    {
        $curr = $node;
        while ($curr !== null) {
            $curr->recalculateBBox();
            $curr = $curr->parent;
        }
    }

    /**
     * Approximate shortest Euclidean/Geodesic distance from a Point to a BoundingBox (meters).
     */
    protected function pointToBoxDistance(Point $point, BoundingBox $box): float
    {
        $clampedLat = max($box->minLat, min($box->maxLat, $point->getLat()));
        $clampedLng = max($box->minLng, min($box->maxLng, $point->getLng()));
        $closestPoint = new Point($clampedLat, $clampedLng);

        return DistanceCalculator::haversine($point, $closestPoint);
    }
}
