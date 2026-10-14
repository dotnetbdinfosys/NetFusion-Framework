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
 * 2PC Phase 1 (Prepare) participant vote decisions.
 */
enum ParticipantVote: string
{
    case VOTE_COMMIT = 'VOTE_COMMIT';
    case VOTE_ABORT = 'VOTE_ABORT';
}
