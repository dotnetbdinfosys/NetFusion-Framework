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

namespace NetFusion\Core\Kube\Scheduler;

use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;

/**
 * Computes priority scores (0 to 100) for candidate nodes using bin-packing / spreading strategies.
 */
class PriorityScorer
{
    /**
     * @param string $strategy 'LeastAllocated' (default spread) or 'MostAllocated' (bin-pack)
     */
    public function __construct(
        protected string $strategy = 'LeastAllocated'
    ) {
    }

    /**
     * Score a node from 0 to 100 for scheduling the given Pod.
     */
    public function score(Pod $pod, Node $node): float
    {
        $allocatable = $node->getAllocatable();
        $allocated = $node->getAllocated();

        $totalCpu = max(1, $allocatable->getCpuMillis());
        $totalMem = max(1, $allocatable->getMemoryMib());

        $freeCpu = max(0, $totalCpu - $allocated->getCpuMillis());
        $freeMem = max(0, $totalMem - $allocated->getMemoryMib());

        $cpuFreeRatio = $freeCpu / $totalCpu;
        $memFreeRatio = $freeMem / $totalMem;

        if ($this->strategy === 'MostAllocated') {
            // Prefer nodes that are already heavily utilized (pack tight)
            $cpuScore = (1.0 - $cpuFreeRatio) * 50.0;
            $memScore = (1.0 - $memFreeRatio) * 50.0;
        } else {
            // LeastAllocated: Prefer nodes with most free capacity (spread evenly)
            $cpuScore = $cpuFreeRatio * 50.0;
            $memScore = $memFreeRatio * 50.0;
        }

        $baseScore = $cpuScore + $memScore;

        // Minor bonus for fewer running pods (tie-breaker for spread)
        $podCount = count($node->getAssignedPods());
        $podPenalty = min(10.0, $podCount * 0.5);

        return max(0.0, min(100.0, $baseScore - $podPenalty));
    }
}
