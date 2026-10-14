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

namespace NetFusion\Core\Consul\Enums;

/**
 * Cluster membership state of a gossip node.
 */
enum NodeStatus: string
{
    case ALIVE   = 'alive';
    case SUSPECT = 'suspect';
    case DEAD    = 'dead';
    case LEFT    = 'left';
}
