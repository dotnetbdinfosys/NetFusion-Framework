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

namespace NetFusion\Core\DistributedDb\MultiMaster;

/**
 * Multi-master cross-region replication synchronization modes.
 */
enum ReplicationMode: string
{
    case ASYNC = 'ASYNC';
    case SYNC_QUORUM = 'SYNC_QUORUM';
    case SEMI_SYNC = 'SEMI_SYNC';
}
