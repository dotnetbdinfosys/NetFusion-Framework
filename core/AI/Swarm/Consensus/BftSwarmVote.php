<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Consensus/BftSwarmVote.php
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

namespace NetFusion\Core\AI\Swarm\Consensus;

/**
 * Cryptographically Verified Byzantine Consensus Vote.
 */
class BftSwarmVote
{
    public function __construct(
        private readonly string $voterId,
        private readonly string $proposalHash,
        private readonly int $round,
        private readonly bool $inFavor = true,
        private readonly string $signature = ''
    ) {
    }

    public function getVoterId(): string
    {
        return $this->voterId;
    }

    public function getProposalHash(): string
    {
        return $this->proposalHash;
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function isInFavor(): bool
    {
        return $this->inFavor;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }
}
