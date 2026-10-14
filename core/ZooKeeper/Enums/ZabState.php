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

namespace NetFusion\Core\ZooKeeper\Enums;

/**
 * ZAB (ZooKeeper Atomic Broadcast) Server Cluster State.
 */
enum ZabState: string
{
    case LOOKING   = 'looking';
    case FOLLOWING = 'following';
    case LEADING   = 'leading';
}
