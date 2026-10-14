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

namespace NetFusion\Core\Scheduling\DAG;

use NetFusion\Core\Scheduling\Models\DAGGraph;
use NetFusion\Core\Scheduling\Exceptions\CycleDetectedException;

class TopologicalSorter
{
    /**
     * Topologically sort DAG nodes using Kahn's algorithm
     *
     * @param DAGGraph $graph
     * @return array<string> List of node IDs in execution order
     * @throws CycleDetectedException
     */
    public function sort(DAGGraph $graph): array
    {
        $nodes = $graph->getNodes();
        $inDegree = [];
        $adjacency = []; // dependent -> nodes that depend on it

        foreach ($nodes as $id => $node) {
            $inDegree[$id] = 0;
            $adjacency[$id] = [];
        }

        foreach ($nodes as $id => $node) {
            foreach ($node->getDependencies() as $depId) {
                if (isset($nodes[$depId])) {
                    $inDegree[$id]++;
                    $adjacency[$depId][] = $id;
                }
            }
        }

        // Queue of nodes with in-degree 0
        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($adjacency[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($nodes)) {
            throw new CycleDetectedException("Cycle detected in DAG graph '{$graph->getName()}'. Unable to topologically sort nodes.");
        }

        return $sorted;
    }

    /**
     * Group topologically sorted nodes into parallel execution stages (layers)
     *
     * @param DAGGraph $graph
     * @return array<int, array<string>>
     * @throws CycleDetectedException
     */
    public function getStages(DAGGraph $graph): array
    {
        $nodes = $graph->getNodes();
        $inDegree = [];
        $adjacency = [];

        foreach ($nodes as $id => $node) {
            $inDegree[$id] = 0;
            $adjacency[$id] = [];
        }

        foreach ($nodes as $id => $node) {
            foreach ($node->getDependencies() as $depId) {
                if (isset($nodes[$depId])) {
                    $inDegree[$id]++;
                    $adjacency[$depId][] = $id;
                }
            }
        }

        $stages = [];
        $currentStage = [];

        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $currentStage[] = $id;
            }
        }

        $processedCount = 0;

        while (!empty($currentStage)) {
            $stages[] = $currentStage;
            $processedCount += count($currentStage);
            $nextStage = [];

            foreach ($currentStage as $nodeId) {
                foreach ($adjacency[$nodeId] as $neighbor) {
                    $inDegree[$neighbor]--;
                    if ($inDegree[$neighbor] === 0) {
                        $nextStage[] = $neighbor;
                    }
                }
            }

            $currentStage = $nextStage;
        }

        if ($processedCount !== count($nodes)) {
            throw new CycleDetectedException("Cycle detected in DAG graph '{$graph->getName()}'. Unable to generate execution stages.");
        }

        return $stages;
    }
}
