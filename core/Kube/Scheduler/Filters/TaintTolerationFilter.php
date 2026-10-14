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

use NetFusion\Core\Kube\Enums\TaintEffect;
use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Scheduler\NodeFilterInterface;

/**
 * Validates that the Pod tolerates all node taints with NoSchedule effect.
 */
class TaintTolerationFilter implements NodeFilterInterface
{
    public function filter(Pod $pod, Node $node): bool
    {
        $taints = $node->getTaints();
        if (empty($taints)) {
            return true;
        }

        $tolerations = $pod->getTolerations();

        foreach ($taints as $taint) {
            // Only NoSchedule taints block scheduling
            if ($taint->getEffect() !== TaintEffect::NO_SCHEDULE) {
                continue;
            }

            $tolerated = false;
            foreach ($tolerations as $toleration) {
                if ($toleration->tolerates($taint)) {
                    $tolerated = true;
                    break;
                }
            }

            if (!$tolerated) {
                return false;
            }
        }

        return true;
    }
}
