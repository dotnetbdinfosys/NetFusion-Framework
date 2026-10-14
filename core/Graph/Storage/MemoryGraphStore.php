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

namespace NetFusion\Core\Graph\Storage;

use NetFusion\Core\Graph\Contracts\GraphStoreInterface;
use NetFusion\Core\Graph\Contracts\NodeInterface;
use NetFusion\Core\Graph\Contracts\EdgeInterface;
use NetFusion\Core\Graph\Exceptions\NodeNotFoundException;

class MemoryGraphStore implements GraphStoreInterface
{
    /**
     * @var array<string, NodeInterface> NodeId => Node
     */
    protected array $nodes = [];

    /**
     * @var array<string, EdgeInterface> EdgeId => Edge
     */
    protected array $edges = [];

    /**
     * @var array<string, array<string, EdgeInterface>> SourceNodeId => [EdgeId => Edge]
     */
    protected array $outEdges = [];

    /**
     * @var array<string, array<string, EdgeInterface>> TargetNodeId => [EdgeId => Edge]
     */
    protected array $inEdges = [];

    /**
     * @var array<string, array<string, bool>> Label => [NodeId => true]
     */
    protected array $labelIndex = [];

    /**
     * @var array<string, array<string, bool>> EdgeType => [EdgeId => true]
     */
    protected array $typeIndex = [];

    public function addNode(NodeInterface $node): static
    {
        $id = $node->getId();
        $this->nodes[$id] = $node;

        if (!isset($this->outEdges[$id])) {
            $this->outEdges[$id] = [];
        }
        if (!isset($this->inEdges[$id])) {
            $this->inEdges[$id] = [];
        }

        foreach ($node->getLabels() as $label) {
            $this->labelIndex[$label][$id] = true;
        }

        return $this;
    }

    public function getNode(string $id): ?NodeInterface
    {
        return $this->nodes[$id] ?? null;
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    public function removeNode(string $id): bool
    {
        if (!isset($this->nodes[$id])) {
            return false;
        }

        // Remove incident edges
        foreach ($this->getOutEdges($id) as $edge) {
            $this->removeEdge($edge->getId());
        }
        foreach ($this->getInEdges($id) as $edge) {
            $this->removeEdge($edge->getId());
        }

        $node = $this->nodes[$id];
        foreach ($node->getLabels() as $label) {
            unset($this->labelIndex[$label][$id]);
        }

        unset($this->nodes[$id]);
        unset($this->outEdges[$id]);
        unset($this->inEdges[$id]);

        return true;
    }

    public function addEdge(EdgeInterface $edge): static
    {
        $sourceId = $edge->getSourceId();
        $targetId = $edge->getTargetId();

        if (!isset($this->nodes[$sourceId])) {
            throw new NodeNotFoundException($sourceId);
        }
        if (!isset($this->nodes[$targetId])) {
            throw new NodeNotFoundException($targetId);
        }

        $id = $edge->getId();
        $this->edges[$id] = $edge;

        $this->outEdges[$sourceId][$id] = $edge;
        $this->inEdges[$targetId][$id] = $edge;

        $type = $edge->getType();
        $this->typeIndex[$type][$id] = true;

        if (!$edge->isDirected()) {
            // Undirected edge: register also in reverse adjacency
            $this->outEdges[$targetId][$id] = $edge;
            $this->inEdges[$sourceId][$id] = $edge;
        }

        return $this;
    }

    public function getEdge(string $id): ?EdgeInterface
    {
        return $this->edges[$id] ?? null;
    }

    public function hasEdge(string $id): bool
    {
        return isset($this->edges[$id]);
    }

    public function removeEdge(string $id): bool
    {
        if (!isset($this->edges[$id])) {
            return false;
        }

        $edge = $this->edges[$id];
        $sourceId = $edge->getSourceId();
        $targetId = $edge->getTargetId();

        unset($this->outEdges[$sourceId][$id]);
        unset($this->inEdges[$targetId][$id]);

        if (!$edge->isDirected()) {
            unset($this->outEdges[$targetId][$id]);
            unset($this->inEdges[$sourceId][$id]);
        }

        unset($this->typeIndex[$edge->getType()][$id]);
        unset($this->edges[$id]);

        return true;
    }

    public function getOutEdges(string $nodeId, ?string $type = null): array
    {
        $edges = array_values($this->outEdges[$nodeId] ?? []);
        if ($type === null) {
            return $edges;
        }
        return array_values(array_filter($edges, fn(EdgeInterface $e) => $e->getType() === $type));
    }

    public function getInEdges(string $nodeId, ?string $type = null): array
    {
        $edges = array_values($this->inEdges[$nodeId] ?? []);
        if ($type === null) {
            return $edges;
        }
        return array_values(array_filter($edges, fn(EdgeInterface $e) => $e->getType() === $type));
    }

    public function getAdjacentNodes(string $nodeId, ?string $type = null, string $direction = 'both'): array
    {
        $neighborIds = [];

        if ($direction === 'out' || $direction === 'both') {
            foreach ($this->getOutEdges($nodeId, $type) as $edge) {
                $otherId = ($edge->getSourceId() === $nodeId) ? $edge->getTargetId() : $edge->getSourceId();
                $neighborIds[$otherId] = true;
            }
        }

        if ($direction === 'in' || $direction === 'both') {
            foreach ($this->getInEdges($nodeId, $type) as $edge) {
                $otherId = ($edge->getTargetId() === $nodeId) ? $edge->getSourceId() : $edge->getTargetId();
                $neighborIds[$otherId] = true;
            }
        }

        $result = [];
        foreach (array_keys($neighborIds) as $nId) {
            if (isset($this->nodes[$nId])) {
                $result[] = $this->nodes[$nId];
            }
        }

        return $result;
    }

    public function getNodes(?string $label = null): array
    {
        if ($label === null) {
            return $this->nodes;
        }

        $nodeIds = array_keys($this->labelIndex[$label] ?? []);
        $result = [];
        foreach ($nodeIds as $nId) {
            if (isset($this->nodes[$nId])) {
                $result[$nId] = $this->nodes[$nId];
            }
        }
        return $result;
    }

    public function getEdges(?string $type = null): array
    {
        if ($type === null) {
            return $this->edges;
        }

        $edgeIds = array_keys($this->typeIndex[$type] ?? []);
        $result = [];
        foreach ($edgeIds as $eId) {
            if (isset($this->edges[$eId])) {
                $result[$eId] = $this->edges[$eId];
            }
        }
        return $result;
    }

    public function countNodes(): int
    {
        return count($this->nodes);
    }

    public function countEdges(): int
    {
        return count($this->edges);
    }

    public function clear(): void
    {
        $this->nodes = [];
        $this->edges = [];
        $this->outEdges = [];
        $this->inEdges = [];
        $this->labelIndex = [];
        $this->typeIndex = [];
    }
}
