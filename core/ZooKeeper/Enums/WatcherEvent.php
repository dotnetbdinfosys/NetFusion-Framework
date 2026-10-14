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
 * Reactive Watcher Event Types.
 */
enum WatcherEvent: string
{
    case NONE                   = 'None';
    case NODE_CREATED           = 'NodeCreated';
    case NODE_DELETED           = 'NodeDeleted';
    case NODE_DATA_CHANGED      = 'NodeDataChanged';
    case NODE_CHILDREN_CHANGED  = 'NodeChildrenChanged';
}
