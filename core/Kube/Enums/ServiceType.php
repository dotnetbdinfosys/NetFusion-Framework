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

namespace NetFusion\Core\Kube\Enums;

/**
 * Kubernetes Service Type.
 */
enum ServiceType: string
{
    case CLUSTER_IP    = 'ClusterIP';
    case NODE_PORT     = 'NodePort';
    case LOAD_BALANCER = 'LoadBalancer';
}
