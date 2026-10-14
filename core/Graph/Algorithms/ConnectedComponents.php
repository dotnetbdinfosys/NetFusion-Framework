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
use SplQueue;

class ConnectedComponents
{
    public function __construct(
        protected GraphStoreInterface $store
    ) {
    }

    /**
     * Find weakly connected components (treating all edges as undirected).
     *
     * @return array<int, array<string>> List of component node ID arrays
     */
    public function findWeaklyConnected(): array
    {
        $allNodes = $this->store->getNodes();
        $visited = [];
        $components = [];

        foreach (array_keys($allNodes) as $nodeId) {
            if (isset($visited[$nodeId])) {
                continue;
            }

            $component = [];
            $queue = new SplQueue();
            $queue->enqueue($nodeId);
            $visited[$nodeId] = true;

            while (!$queue->isEmpty()) {
                $currId = $queue->dequeue();
                $component[] = $currId;

                $neighbors = $this->store->getAdjacentNodes($currId, direction: 'both');
                foreach ($neighbors as $neighbor) {
                    $nId = $neighbor->getId();
                    if (!isset($visited[$nId])) {
                        $visited[$nId] = true;
                        $queue->enqueue($nId);
                    }
                }
            }

            $components[] = $component;
        }

        return $components;
    }
}
