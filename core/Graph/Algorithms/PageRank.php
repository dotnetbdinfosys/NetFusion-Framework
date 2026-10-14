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

use NetFusion\Core\Graph\Contracts\GraphStoreInterface;

class PageRank
{
    /**
     * @param GraphStoreInterface $store
     * @param float $dampingFactor Probability of following a link (typically 0.85)
     * @param int $maxIterations
     * @param float $tolerance Convergence epsilon
     */
    public function __construct(
        protected GraphStoreInterface $store,
        protected float $dampingFactor = 0.85,
        protected int $maxIterations = 100,
        protected float $tolerance = 1e-6
    ) {
    }

    /**
     * Compute PageRank scores for all nodes in the graph.
     *
     * @return array<string, float> NodeId => Normalized PageRank score (sums to 1.0)
     */
    public function compute(): array
    {
        $allNodes = $this->store->getNodes();
        $numNodes = count($allNodes);

        if ($numNodes === 0) {
            return [];
        }

        $nodeIds = array_keys($allNodes);
        $initialRank = 1.0 / $numNodes;
        $scores = array_fill_keys($nodeIds, $initialRank);

        // Precompute out-degree and in-neighbors
        $outDegrees = [];
        $inNeighbors = [];

        foreach ($nodeIds as $id) {
            $outEdges = $this->store->getOutEdges($id);
            $outDegrees[$id] = count($outEdges);

            $inEdges = $this->store->getInEdges($id);
            $inList = [];
            foreach ($inEdges as $edge) {
                $src = $edge->getSourceId();
                if ($src !== $id) {
                    $inList[] = $src;
                }
            }
            $inNeighbors[$id] = $inList;
        }

        for ($iter = 0; $iter < $this->maxIterations; $iter++) {
            $newScores = [];
            $danglingWeight = 0.0;

            // Compute total rank of nodes with no outgoing links (dangling nodes)
            foreach ($nodeIds as $id) {
                if ($outDegrees[$id] === 0) {
                    $danglingWeight += $scores[$id];
                }
            }

            $baseScore = (1.0 - $this->dampingFactor) / $numNodes + ($this->dampingFactor * $danglingWeight / $numNodes);

            $diff = 0.0;
            foreach ($nodeIds as $id) {
                $incomingSum = 0.0;
                foreach ($inNeighbors[$id] as $inSrcId) {
                    $outDeg = $outDegrees[$inSrcId];
                    if ($outDeg > 0) {
                        $incomingSum += $scores[$inSrcId] / $outDeg;
                    }
                }

                $newScore = $baseScore + ($this->dampingFactor * $incomingSum);
                $newScores[$id] = $newScore;
                $diff += abs($newScore - $scores[$id]);
            }

            $scores = $newScores;

            if ($diff < $this->tolerance) {
                break;
            }
        }

        // Sort descending by rank score
        arsort($scores);

        return $scores;
    }
}
