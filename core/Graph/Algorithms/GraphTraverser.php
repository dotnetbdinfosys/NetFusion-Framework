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

namespace NetFusion\Core\Graph\Algorithms;

use NetFusion\Core\Graph\Contracts\TraverserInterface;
use NetFusion\Core\Graph\Contracts\GraphStoreInterface;
use NetFusion\Core\Graph\Contracts\NodeInterface;
use NetFusion\Core\Graph\Contracts\EdgeInterface;
use NetFusion\Core\Graph\Models\Path;
use SplQueue;
use SplPriorityQueue;

class GraphTraverser implements TraverserInterface
{
    public function __construct(
        protected GraphStoreInterface $store
    ) {
    }

    public function bfs(string $startNodeId, int $maxDepth = PHP_INT_MAX, ?callable $visitor = null): array
    {
        $startNode = $this->store->getNode($startNodeId);
        if ($startNode === null) {
            return [];
        }

        $visited = [$startNodeId => 0];
        $queue = new SplQueue();
        $queue->enqueue([$startNode, 0]);

        while (!$queue->isEmpty()) {
            [$currentNode, $depth] = $queue->dequeue();

            if ($visitor !== null) {
                $continue = $visitor($currentNode, $depth);
                if ($continue === false) {
                    continue;
                }
            }

            if ($depth >= $maxDepth) {
                continue;
            }

            foreach ($this->store->getOutEdges($currentNode->getId()) as $edge) {
                $targetId = ($edge->getSourceId() === $currentNode->getId()) ? $edge->getTargetId() : $edge->getSourceId();
                if (!isset($visited[$targetId])) {
                    $visited[$targetId] = $depth + 1;
                    $targetNode = $this->store->getNode($targetId);
                    if ($targetNode !== null) {
                        $queue->enqueue([$targetNode, $depth + 1]);
                    }
                }
            }
        }

        return $visited;
    }

    public function dfs(string $startNodeId, int $maxDepth = PHP_INT_MAX, ?callable $visitor = null): array
    {
        $visited = [];
        $order = [];
        $this->dfsRecursive($startNodeId, 0, $maxDepth, $visited, $order, $visitor);
        return $order;
    }

    protected function dfsRecursive(string $nodeId, int $depth, int $maxDepth, array &$visited, array &$order, ?callable $visitor): void
    {
        if (isset($visited[$nodeId]) || $depth > $maxDepth) {
            return;
        }

        $node = $this->store->getNode($nodeId);
        if ($node === null) {
            return;
        }

        $visited[$nodeId] = true;
        $order[] = $nodeId;

        if ($visitor !== null) {
            $continue = $visitor($node, $depth);
            if ($continue === false) {
                return;
            }
        }

        foreach ($this->store->getOutEdges($nodeId) as $edge) {
            $targetId = ($edge->getSourceId() === $nodeId) ? $edge->getTargetId() : $edge->getSourceId();
            $this->dfsRecursive($targetId, $depth + 1, $maxDepth, $visited, $order, $visitor);
        }
    }

    public function shortestPath(string $startNodeId, string $targetNodeId, ?string $relationshipType = null): ?Path
    {
        if ($startNodeId === $targetNodeId) {
            $node = $this->store->getNode($startNodeId);
            return $node !== null ? new Path([$node], [], 0.0) : null;
        }

        $distances = [$startNodeId => 0.0];
        $previousNodes = [];
        $previousEdges = [];

        // Priority queue storing [nodeId, distance] with min-heap priority
        $pq = new class extends SplPriorityQueue {
            public function compare(mixed $priority1, mixed $priority2): int
            {
                // Invert comparison for Min-Heap
                return ($priority1 > $priority2) ? -1 : (($priority1 < $priority2) ? 1 : 0);
            }
        };

        $pq->insert($startNodeId, 0.0);
        $visited = [];

        while (!$pq->isEmpty()) {
            $currentId = $pq->extract();

            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            if ($currentId === $targetNodeId) {
                break;
            }

            $currentDist = $distances[$currentId] ?? INF;

            foreach ($this->store->getOutEdges($currentId, $relationshipType) as $edge) {
                $targetId = ($edge->getSourceId() === $currentId) ? $edge->getTargetId() : $edge->getSourceId();
                $newDist = $currentDist + max(0.0001, $edge->getWeight());

                if ($newDist < ($distances[$targetId] ?? INF)) {
                    $distances[$targetId] = $newDist;
                    $previousNodes[$targetId] = $currentId;
                    $previousEdges[$targetId] = $edge;
                    $pq->insert($targetId, $newDist);
                }
            }
        }

        if (!isset($distances[$targetNodeId]) || $distances[$targetNodeId] === INF) {
            return null;
        }

        // Reconstruct path
        $pathNodes = [];
        $pathEdges = [];
        $curr = $targetNodeId;

        while ($curr !== null) {
            $n = $this->store->getNode($curr);
            if ($n !== null) {
                array_unshift($pathNodes, $n);
            }
            if (isset($previousEdges[$curr])) {
                array_unshift($pathEdges, $previousEdges[$curr]);
            }
            $curr = $previousNodes[$curr] ?? null;
        }

        return new Path($pathNodes, $pathEdges, $distances[$targetNodeId]);
    }

    public function allPaths(string $startNodeId, string $targetNodeId, int $maxHops = 5, ?string $relationshipType = null): array
    {
        $allPaths = [];
        $startNode = $this->store->getNode($startNodeId);
        if ($startNode === null) {
            return [];
        }

        $this->findPathsRecursive($startNode, $targetNodeId, [$startNode], [], 0.0, $maxHops, $relationshipType, $allPaths);
        return $allPaths;
    }

    protected function findPathsRecursive(
        NodeInterface $current,
        string $targetId,
        array $currentNodes,
        array $currentEdges,
        float $currentWeight,
        int $maxHops,
        ?string $relationshipType,
        array &$allPaths
    ): void {
        if ($current->getId() === $targetId) {
            $allPaths[] = new Path($currentNodes, $currentEdges, $currentWeight);
            return;
        }

        if (count($currentEdges) >= $maxHops) {
            return;
        }

        foreach ($this->store->getOutEdges($current->getId(), $relationshipType) as $edge) {
            $nextId = ($edge->getSourceId() === $current->getId()) ? $edge->getTargetId() : $edge->getSourceId();

            // Prevent node cycles in simple path
            $alreadyVisited = false;
            foreach ($currentNodes as $n) {
                if ($n->getId() === $nextId) {
                    $alreadyVisited = true;
                    break;
                }
            }

            if (!$alreadyVisited) {
                $nextNode = $this->store->getNode($nextId);
                if ($nextNode !== null) {
                    $newNodes = $currentNodes;
                    $newNodes[] = $nextNode;

                    $newEdges = $currentEdges;
                    $newEdges[] = $edge;

                    $this->findPathsRecursive(
                        $nextNode,
                        $targetId,
                        $newNodes,
                        $newEdges,
                        $currentWeight + $edge->getWeight(),
                        $maxHops,
                        $relationshipType,
                        $allPaths
                    );
                }
            }
        }
    }
}
