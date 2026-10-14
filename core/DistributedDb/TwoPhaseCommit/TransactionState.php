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

namespace NetFusion\Core\DistributedDb\TwoPhaseCommit;

/**
 * Distributed Two-Phase Commit (2PC) transaction states.
 */
enum TransactionState: string
{
    case INITIAL = 'INITIAL';
    case PREPARING = 'PREPARING';
    case PREPARED = 'PREPARED';
    case COMMITTING = 'COMMITTING';
    case COMMITTED = 'COMMITTED';
    case ABORTING = 'ABORTING';
    case ABORTED = 'ABORTED';
}
