<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Auction/AgentBid.php
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

namespace NetFusion\Core\AI\Swarm\Auction;

/**
 * Competitive Auction Bid submitted by a Worker Agent.
 */
class AgentBid
{
    public function __construct(
        private readonly string $agentId,
        private readonly string $taskId,
        private readonly float $bidCost,
        private readonly float $bidScore,
        private readonly float $estimatedTimeMs = 100.0
    ) {
    }

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getBidCost(): float
    {
        return $this->bidCost;
    }

    public function getBidScore(): float
    {
        return $this->bidScore;
    }

    public function getEstimatedTimeMs(): float
    {
        return $this->estimatedTimeMs;
    }
}
