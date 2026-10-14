<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\DistributedDb\DistributedSql;

/**
 * Sharding and partitioning strategies for distributed tables.
 */
enum PartitionStrategy: string
{
    case HASH = 'HASH';
    case RANGE = 'RANGE';
    case LIST = 'LIST';
    case BROADCAST = 'BROADCAST';
}
