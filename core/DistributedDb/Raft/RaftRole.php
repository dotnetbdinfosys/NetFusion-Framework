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

namespace NetFusion\Core\DistributedDb\Raft;

/**
 * Raft node role states conforming to the Raft consensus algorithm.
 */
enum RaftRole: string
{
    case LEADER = 'LEADER';
    case FOLLOWER = 'FOLLOWER';
    case CANDIDATE = 'CANDIDATE';
}
