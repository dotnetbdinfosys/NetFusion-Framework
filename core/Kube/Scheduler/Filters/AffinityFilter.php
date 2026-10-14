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
 * Validates that a node satisfies the Pod's nodeSelector label requirements.
 */
class AffinityFilter implements NodeFilterInterface
{
    public function filter(Pod $pod, Node $node): bool
    {
        $selector = $pod->getNodeSelector();
        if (empty($selector)) {
            return true;
        }

        $nodeLabels = $node->getLabels();
        foreach ($selector as $key => $expectedValue) {
            if (!isset($nodeLabels[$key]) || $nodeLabels[$key] !== $expectedValue) {
                return false;
            }
        }

        return true;
    }
}
