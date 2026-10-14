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

namespace NetFusion\Core\Graph\Models;

use NetFusion\Core\Graph\Contracts\EdgeInterface;
use NetFusion\Core\Graph\Contracts\NodeInterface;

class Path
{
    /**
     * @param array<NodeInterface> $nodes
     * @param array<EdgeInterface> $edges
     * @param float $totalWeight
     */
    public function __construct(
        protected array $nodes = [],
        protected array $edges = [],
        protected float $totalWeight = 0.0
    ) {
    }

    /**
     * @return array<NodeInterface>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return array<string>
     */
    public function getNodeIds(): array
    {
        return array_map(fn(NodeInterface $n) => $n->getId(), $this->nodes);
    }

    /**
     * @return array<EdgeInterface>
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    public function getStartNode(): ?NodeInterface
    {
        return $this->nodes[0] ?? null;
    }

    public function getEndNode(): ?NodeInterface
    {
        $count = count($this->nodes);
        return $count > 0 ? $this->nodes[$count - 1] : null;
    }

    public function getLength(): int
    {
        return count($this->edges);
    }

    public function getTotalWeight(): float
    {
        return $this->totalWeight;
    }

    public function toArray(): array
    {
        return [
            'nodes' => array_map(fn(NodeInterface $n) => $n->toArray(), $this->nodes),
            'edges' => array_map(fn(EdgeInterface $e) => $e->toArray(), $this->edges),
            'length' => $this->getLength(),
            'total_weight' => $this->totalWeight,
        ];
    }
}
