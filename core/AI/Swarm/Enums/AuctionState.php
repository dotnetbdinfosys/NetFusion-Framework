<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Enums/AuctionState.php
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

namespace NetFusion\Core\AI\Swarm\Enums;

/**
 * State machine of Contract Net Protocol (CNP) task auctions.
 */
enum AuctionState: string
{
    case ANNOUNCED = 'announced'; // Call for Proposals (CFP) broadcasted
    case BIDDING   = 'bidding';   // Collecting bids from available worker agents
    case AWARDED   = 'awarded';   // Winning bid selected and contract awarded
    case EXECUTING = 'executing'; // Task currently running on chosen worker agent
    case COMPLETED = 'completed'; // Result delivered and verified
    case FAILED    = 'failed';    // Execution failure or timeout reached
}
