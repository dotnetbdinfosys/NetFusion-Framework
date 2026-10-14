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

namespace NetFusion\Core\Kafka\Enums;

/**
 * Consumer Group rebalance lifecycle states.
 */
enum RebalanceState: string
{
    case STABLE               = 'stable';
    case PREPARING_REBALANCE  = 'preparing_rebalance';
    case COMPLETING_REBALANCE = 'completing_rebalance';
    case DEAD                 = 'dead';
}
