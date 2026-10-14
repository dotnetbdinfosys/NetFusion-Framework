<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Auction/TaskProposal.php
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
 * Task Specification & Call for Proposal (CFP) Descriptor.
 */
class TaskProposal
{
    public function __construct(
        private readonly string $id,
        private readonly string $taskType,
        private readonly float $estimatedEffort = 1.0,
        private readonly int $originX = 0,
        private readonly int $originY = 0,
        /** @var array<string, mixed> */
        private readonly array $payload = [],
        private readonly float $timeoutMs = 5000.0
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTaskType(): string
    {
        return $this->taskType;
    }

    public function getEstimatedEffort(): float
    {
        return $this->estimatedEffort;
    }

    public function getOriginX(): int
    {
        return $this->originX;
    }

    public function getOriginY(): int
    {
        return $this->originY;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getTimeoutMs(): float
    {
        return $this->timeoutMs;
    }
}
