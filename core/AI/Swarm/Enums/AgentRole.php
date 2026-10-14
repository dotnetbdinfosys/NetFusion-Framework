<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Enums/AgentRole.php
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
 * Functional roles assumed by autonomous agents in the swarm.
 */
enum AgentRole: string
{
    case SCOUT       = 'scout';       // Exploratory agent depositing environmental trails
    case WORKER      = 'worker';      // Task executing agent bidding in Contract Net auctions
    case AUCTIONEER  = 'auctioneer';  // Task initiator broadcasting Call for Proposals (CFP)
    case VALIDATOR   = 'validator';   // Byzantine consensus voting and quorum certifying agent

    public function description(): string
    {
        return match ($this) {
            self::SCOUT      => 'Explores solution space and marks attractive gradients via pheromones',
            self::WORKER     => 'Processes computational work units and submits dynamic auction bids',
            self::AUCTIONEER => 'Splits distributed jobs and orchestrates Contract Net Protocol auctions',
            self::VALIDATOR  => 'Verifies state transitions and signs Byzantine Quorum Certificates',
        };
    }
}
