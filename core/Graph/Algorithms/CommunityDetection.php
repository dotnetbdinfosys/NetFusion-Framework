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

class CommunityDetection
{
    public function __construct(
        protected GraphStoreInterface $store,
        protected int $maxIterations = 50
    ) {
    }

    /**
     * Detect community clusters using Label Propagation Algorithm (LPA).
     *
     * @return array<string, int|string> NodeId => CommunityId
     */
    public function detect(): array
    {
        $allNodes = $this->store->getNodes();
        if (empty($allNodes)) {
            return [];
        }

        // Initialize each node in its own community
        $labels = [];
        $nodeIds = array_keys($allNodes);
        foreach ($nodeIds as $id) {
            $labels[$id] = $id;
        }

        for ($iter = 0; $iter < $this->maxIterations; $iter++) {
            $changed = false;
            // Shuffle node order to prevent oscillation
            shuffle($nodeIds);

            foreach ($nodeIds as $id) {
                $neighborLabels = [];

                // Count labels of adjacent neighbors
                $neighbors = $this->store->getAdjacentNodes($id, direction: 'both');
                foreach ($neighbors as $neighbor) {
                    $nLabel = $labels[$neighbor->getId()];
                    $neighborLabels[$nLabel] = ($neighborLabels[$nLabel] ?? 0) + 1;
                }

                if (empty($neighborLabels)) {
                    continue;
                }

                // Pick most frequent label among neighbors
                arsort($neighborLabels);
                $dominantLabel = array_key_first($neighborLabels);

                if ($labels[$id] !== $dominantLabel) {
                    $labels[$id] = $dominantLabel;
                    $changed = true;
                }
            }

            if (!$changed) {
                break;
            }
        }

        return $labels;
    }

    /**
     * Group node IDs by detected community ID.
     *
     * @return array<string, array<string>> CommunityId => array of NodeIds
     */
    public function getClusters(): array
    {
        $labels = $this->detect();
        $clusters = [];
        foreach ($labels as $nodeId => $commId) {
            $clusters[$commId][] = $nodeId;
        }
        return array_values($clusters);
    }
}
