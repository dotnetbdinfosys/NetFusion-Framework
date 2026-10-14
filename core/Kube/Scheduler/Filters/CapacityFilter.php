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

namespace NetFusion\Core\Kube\Scheduler\Filters;

use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Scheduler\NodeFilterInterface;

/**
 * Validates that a node is Ready and has sufficient CPU and Memory capacity for the Pod.
 */
class CapacityFilter implements NodeFilterInterface
{
    public function filter(Pod $pod, Node $node): bool
    {
        if (!$node->isReady()) {
            return false;
        }

        $needed = $pod->getTotalResources();
        return $node->hasCapacity($needed);
    }
}
