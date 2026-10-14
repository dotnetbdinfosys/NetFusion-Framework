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
 * Contract for Kubernetes Scheduler node predicate/filtering plugins.
 */
interface NodeFilterInterface
{
    /**
     * Determine if a node satisfies the predicate requirements for scheduling the given Pod.
     */
    public function filter(Pod $pod, Node $node): bool;
}
