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

namespace NetFusion\Core\GraphRag\Graph;

use NetFusion\Core\GraphRag\Entity\KnowledgeEdge;
use NetFusion\Core\GraphRag\Entity\KnowledgeNode;
use NetFusion\Core\GraphRag\Index\Bm25Index;
use NetFusion\Core\GraphRag\Index\VectorSimilarity;

/**
 * Hybrid Vector Knowledge Graph combining dense embedding spaces, property graph topology, and sparse lexical indexing.
 */
class VectorKnowledgeGraph
{
    /** @var array<string, KnowledgeNode> nodeId => KnowledgeNode */
    protected array $nodes = [];

    /** @var array<string, array<int, KnowledgeEdge>> sourceId => edges */
    protected array $outgoingEdges = [];

    /** @var array<string, array<int, KnowledgeEdge>> targetId => edges */
    protected array $incomingEdges = [];

    protected Bm25Index $bm25;

    public function __construct(?Bm25Index $bm25 = null)
    {
        $this->bm25 = $bm25 ?? new Bm25Index();
    }

    public function getBm25Index(): Bm25Index
    {
        return $this->bm25;
    }

    public function addNode(KnowledgeNode $node): self
    {
        $this->nodes[$node->getId()] = $node;

        if ($node->getContent() !== '') {
            $this->bm25->addDocument($node->getId(), $node->getContent());
        }

        return $this;
    }

    public function getNode(string $id): ?KnowledgeNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * @return array<string, KnowledgeNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function countNodes(): int
    {
        return count($this->nodes);
    }

    public function removeNode(string $id): bool
    {
        if (!isset($this->nodes[$id])) {
            return false;
        }

        unset($this->nodes[$id]);
        $this->bm25->removeDocument($id);

        unset($this->outgoingEdges[$id]);
        unset($this->incomingEdges[$id]);

        // Remove incoming/outgoing references in other adjacency lists
        foreach ($this->outgoingEdges as $src => $edges) {
            $this->outgoingEdges[$src] = array_values(array_filter(
                $edges,
                fn(KnowledgeEdge $e) => $e->getTargetId() !== $id
            ));
        }

        foreach ($this->incomingEdges as $tgt => $edges) {
            $this->incomingEdges[$tgt] = array_values(array_filter(
                $edges,
                fn(KnowledgeEdge $e) => $e->getSourceId() !== $id
            ));
        }

        return true;
    }

    public function addEdge(KnowledgeEdge $edge): self
    {
        $this->outgoingEdges[$edge->getSourceId()][] = $edge;
        $this->incomingEdges[$edge->getTargetId()][] = $edge;
        return $this;
    }

    /**
     * Retrieve edges connected to a node, or all edges if nodeId is null.
     *
     * @return array<int, KnowledgeEdge>
     */
    public function getEdges(?string $nodeId = null): array
    {
        if ($nodeId === null) {
            $all = [];
            foreach ($this->outgoingEdges as $edges) {
                foreach ($edges as $e) {
                    $all[] = $e;
                }
            }
            return $all;
        }

        $out = $this->outgoingEdges[$nodeId] ?? [];
        $in = $this->incomingEdges[$nodeId] ?? [];
        return array_merge($out, $in);
    }

    public function countEdges(): int
    {
        $count = 0;
        foreach ($this->outgoingEdges as $edges) {
            $count += count($edges);
        }
        return $count;
    }

    /**
     * Compute in-degree + out-degree centrality of a node.
     */
    public function getNodeDegree(string $nodeId): int
    {
        $out = count($this->outgoingEdges[$nodeId] ?? []);
        $in = count($this->incomingEdges[$nodeId] ?? []);
        return $out + $in;
    }

    /**
     * Traverse graph in k-hops from a start node, extracting the subgraph neighborhood.
     *
     * @param string $startNodeId
     * @param int $depth Maximum hop depth
     * @param float $minWeight Minimum edge weight threshold
     * @return array{nodes: array<string, KnowledgeNode>, edges: array<int, KnowledgeEdge>}
     */
    public function kHopExpansion(string $startNodeId, int $depth = 1, float $minWeight = 0.0): array
    {
        if (!isset($this->nodes[$startNodeId])) {
            return ['nodes' => [], 'edges' => []];
        }

        $visitedNodes = [$startNodeId => $this->nodes[$startNodeId]];
        $visitedEdges = [];
        $currentFrontier = [$startNodeId];

        for ($d = 0; $d < $depth; $d++) {
            $nextFrontier = [];

            foreach ($currentFrontier as $currentId) {
                // Outgoing edges
                foreach ($this->outgoingEdges[$currentId] ?? [] as $edge) {
                    if ($edge->getWeight() >= $minWeight) {
                        $tgtId = $edge->getTargetId();
                        $visitedEdges[] = $edge;

                        if (!isset($visitedNodes[$tgtId]) && isset($this->nodes[$tgtId])) {
                            $visitedNodes[$tgtId] = $this->nodes[$tgtId];
                            $nextFrontier[] = $tgtId;
                        }
                    }
                }

                // Incoming edges
                foreach ($this->incomingEdges[$currentId] ?? [] as $edge) {
                    if ($edge->getWeight() >= $minWeight) {
                        $srcId = $edge->getSourceId();
                        $visitedEdges[] = $edge;

                        if (!isset($visitedNodes[$srcId]) && isset($this->nodes[$srcId])) {
                            $visitedNodes[$srcId] = $this->nodes[$srcId];
                            $nextFrontier[] = $srcId;
                        }
                    }
                }
            }

            $currentFrontier = array_unique($nextFrontier);
            if (empty($currentFrontier)) {
                break;
            }
        }

        return [
            'nodes' => $visitedNodes,
            'edges' => $visitedEdges,
        ];
    }

    /**
     * Dense vector similarity search across all indexed nodes with vectors.
     *
     * @param array<int, float> $queryVector
     * @param int $limit
     * @param float $minSimilarity
     * @return array<int, array{node: KnowledgeNode, similarity: float}>
     */
    public function findDenseNeighbors(array $queryVector, int $limit = 5, float $minSimilarity = 0.0): array
    {
        $matches = [];

        foreach ($this->nodes as $node) {
            if (!$node->hasVector()) {
                continue;
            }

            try {
                $sim = VectorSimilarity::cosine($queryVector, $node->getVector());
                if ($sim >= $minSimilarity) {
                    $matches[] = [
                        'node'       => $node,
                        'similarity' => $sim,
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        usort($matches, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        return array_slice($matches, 0, $limit);
    }

    /**
     * Sparse BM25 lexical search across all nodes.
     *
     * @param string $queryText
     * @param int $limit
     * @return array<int, array{node: KnowledgeNode, score: float}>
     */
    public function findSparseNeighbors(string $queryText, int $limit = 5): array
    {
        $bm25Scores = $this->bm25->search($queryText, $limit);
        $results = [];

        foreach ($bm25Scores as $docId => $score) {
            if (isset($this->nodes[$docId])) {
                $results[] = [
                    'node'  => $this->nodes[$docId],
                    'score' => (float) $score,
                ];
            }
        }

        return $results;
    }
}
