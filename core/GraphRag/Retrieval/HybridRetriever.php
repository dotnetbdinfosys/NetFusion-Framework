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

namespace NetFusion\Core\GraphRag\Retrieval;

use NetFusion\Core\GraphRag\Entity\KnowledgeNode;
use NetFusion\Core\GraphRag\Enums\FusionStrategy;
use NetFusion\Core\GraphRag\Graph\VectorKnowledgeGraph;

/**
 * Orchestrates Tri-Way Hybrid Retrieval: Dense Vectors + Sparse BM25 + Graph Neighborhood Traversal.
 */
class HybridRetriever
{
    public function __construct(
        protected VectorKnowledgeGraph $graph,
        protected int $rrfConstant = 60,
        protected float $denseWeight = 0.5,
        protected float $sparseWeight = 0.3,
        protected float $graphWeight = 0.2
    ) {
    }

    /**
     * Perform Tri-Way Hybrid Retrieval combining dense vector matching, sparse BM25, and relational graph expansion.
     *
     * @param string $query
     * @param array<int, float> $queryVector
     * @param int $limit
     * @param FusionStrategy $strategy
     * @param int $graphHopDepth
     * @return array<int, array{node: KnowledgeNode, score: float, rank: int, matchTypes: array<int, string>}>
     */
    public function retrieve(
        string $query,
        array $queryVector = [],
        int $limit = 5,
        FusionStrategy $strategy = FusionStrategy::RECIPROCAL_RANK_FUSION,
        int $graphHopDepth = 1
    ): array {
        // 1. Dense vector candidate retrieval
        $denseResults = [];
        if (!empty($queryVector)) {
            $denseResults = $this->graph->findDenseNeighbors($queryVector, $limit * 2);
        }

        // 2. Sparse BM25 candidate retrieval
        $sparseResults = [];
        if ($query !== '') {
            $sparseResults = $this->graph->findSparseNeighbors($query, $limit * 2);
        }

        // 3. Graph Relational Neighborhood expansion from top seeds
        $topSeedIds = [];
        foreach (array_slice($denseResults, 0, 2) as $item) {
            $topSeedIds[] = $item['node']->getId();
        }
        foreach (array_slice($sparseResults, 0, 2) as $item) {
            $topSeedIds[] = $item['node']->getId();
        }
        $topSeedIds = array_unique($topSeedIds);

        $graphExpandedNodes = [];
        foreach ($topSeedIds as $seedId) {
            $expansion = $this->graph->kHopExpansion($seedId, $graphHopDepth);
            foreach ($expansion['nodes'] as $nodeId => $node) {
                if (!isset($graphExpandedNodes[$nodeId])) {
                    $graphExpandedNodes[$nodeId] = $node;
                }
            }
        }

        // 4. Fusion Strategy
        if ($strategy === FusionStrategy::RECIPROCAL_RANK_FUSION) {
            $fused = $this->fuseWithRrf($denseResults, $sparseResults, $graphExpandedNodes);
        } else {
            $fused = $this->fuseWithWeightedScore($denseResults, $sparseResults, $graphExpandedNodes);
        }

        usort($fused, fn($a, $b) => $b['score'] <=> $a['score']);
        $sliced = array_slice($fused, 0, $limit);

        // Assign ordinal ranks
        $rank = 1;
        foreach ($sliced as &$item) {
            $item['rank'] = $rank++;
        }

        return $sliced;
    }

    /**
     * Compute Reciprocal Rank Fusion (RRF): RRF_score(d) = sum( 1 / (k + rank) ).
     *
     * @param array<int, array{node: KnowledgeNode, similarity: float}> $dense
     * @param array<int, array{node: KnowledgeNode, score: float}> $sparse
     * @param array<string, KnowledgeNode> $graphNodes
     * @return array<int, array{node: KnowledgeNode, score: float, matchTypes: array<int, string>}>
     */
    protected function fuseWithRrf(array $dense, array $sparse, array $graphNodes): array
    {
        $nodeMap = [];
        $scores = [];
        $matchTypes = [];

        // Dense rankings
        $rank = 1;
        foreach ($dense as $item) {
            $id = $item['node']->getId();
            $nodeMap[$id] = $item['node'];
            $scores[$id] = ($scores[$id] ?? 0.0) + (1.0 / ($this->rrfConstant + $rank));
            $matchTypes[$id][] = 'dense_vector';
            $rank++;
        }

        // Sparse rankings
        $rank = 1;
        foreach ($sparse as $item) {
            $id = $item['node']->getId();
            $nodeMap[$id] = $item['node'];
            $scores[$id] = ($scores[$id] ?? 0.0) + (1.0 / ($this->rrfConstant + $rank));
            $matchTypes[$id][] = 'sparse_bm25';
            $rank++;
        }

        // Graph neighborhood bonus
        $rank = 1;
        foreach ($graphNodes as $id => $node) {
            $nodeMap[$id] = $node;
            $scores[$id] = ($scores[$id] ?? 0.0) + (0.5 / ($this->rrfConstant + $rank));
            $matchTypes[$id][] = 'graph_relational';
            $rank++;
        }

        $results = [];
        foreach ($scores as $id => $score) {
            $results[] = [
                'node'       => $nodeMap[$id],
                'score'      => round($score, 6),
                'matchTypes' => array_values(array_unique($matchTypes[$id] ?? [])),
            ];
        }

        return $results;
    }

    /**
     * Linear normalized weighted score fusion.
     *
     * @param array<int, array{node: KnowledgeNode, similarity: float}> $dense
     * @param array<int, array{node: KnowledgeNode, score: float}> $sparse
     * @param array<string, KnowledgeNode> $graphNodes
     * @return array<int, array{node: KnowledgeNode, score: float, matchTypes: array<int, string>}>
     */
    protected function fuseWithWeightedScore(array $dense, array $sparse, array $graphNodes): array
    {
        $nodeMap = [];
        $scores = [];
        $matchTypes = [];

        // Max sparse score for normalization
        $maxSparse = 1.0;
        foreach ($sparse as $s) {
            if ($s['score'] > $maxSparse) {
                $maxSparse = $s['score'];
            }
        }

        foreach ($dense as $d) {
            $id = $d['node']->getId();
            $nodeMap[$id] = $d['node'];
            $normSim = max(0.0, min(1.0, ($d['similarity'] + 1.0) / 2.0));
            $scores[$id] = ($scores[$id] ?? 0.0) + ($normSim * $this->denseWeight);
            $matchTypes[$id][] = 'dense_vector';
        }

        foreach ($sparse as $s) {
            $id = $s['node']->getId();
            $nodeMap[$id] = $s['node'];
            $normBm25 = $s['score'] / $maxSparse;
            $scores[$id] = ($scores[$id] ?? 0.0) + ($normBm25 * $this->sparseWeight);
            $matchTypes[$id][] = 'sparse_bm25';
        }

        foreach ($graphNodes as $id => $node) {
            $nodeMap[$id] = $node;
            $degree = $this->graph->getNodeDegree($id);
            $normGraph = min(1.0, $degree / 5.0);
            $scores[$id] = ($scores[$id] ?? 0.0) + ($normGraph * $this->graphWeight);
            $matchTypes[$id][] = 'graph_relational';
        }

        $results = [];
        foreach ($scores as $id => $score) {
            $results[] = [
                'node'       => $nodeMap[$id],
                'score'      => round($score, 6),
                'matchTypes' => array_values(array_unique($matchTypes[$id] ?? [])),
            ];
        }

        return $results;
    }
}
