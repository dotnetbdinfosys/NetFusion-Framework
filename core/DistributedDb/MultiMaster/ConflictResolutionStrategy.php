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
 * Strategies for resolving concurrent divergent multi-master mutations.
 */
enum ConflictResolutionStrategy: string
{
    case LAST_WRITE_WINS = 'LAST_WRITE_WINS';
    case VECTOR_CLOCK = 'VECTOR_CLOCK';
    case CRDT_MERGE = 'CRDT_MERGE';
    case CUSTOM_CALLABLE = 'CUSTOM_CALLABLE';
}
