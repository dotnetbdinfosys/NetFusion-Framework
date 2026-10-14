<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Agents/SwarmAgent.php
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

namespace NetFusion\Core\AI\Swarm\Agents;

use NetFusion\Core\AI\Swarm\Enums\AgentRole;
use NetFusion\Core\AI\Swarm\Enums\PheromoneType;
use NetFusion\Core\AI\Swarm\Pheromone\PheromoneGrid;
use NetFusion\Core\AI\Swarm\Auction\TaskProposal;
use NetFusion\Core\AI\Swarm\Auction\AgentBid;

/**
 * Autonomous Swarm Agent entity with spatial mobility,
 * stigmergic perception, and auction bidding capabilities.
 */
class SwarmAgent
{
    private float $capacity = 1.0; // 1.0 = fully idle, 0.0 = fully busy
    private float $reputation = 1.0;
    private int $tasksCompleted = 0;
    private ?string $currentTaskId = null;

    public function __construct(
        private readonly string $id,
        private AgentRole $role = AgentRole::WORKER,
        private int $x = 0,
        private int $y = 0,
        private readonly float $processingPower = 1.0 // relative CPU/Compute factor
    ) {
    }

    /**
     * Stigmergic motion step: follow positive gradient or explore, then deposit trail.
     */
    public function stepStigmergic(PheromoneGrid $grid, float $explorationChance = 0.15): array
    {
        if ((mt_rand(1, 1000) / 1000.0) <= $explorationChance) {
            // Exploratory random step (Lévy flight / random walk)
            $dx = mt_rand(-1, 1);
            $dy = mt_rand(-1, 1);
            $newX = max(0, min($grid->getWidth() - 1, $this->x + $dx));
            $newY = max(0, min($grid->getHeight() - 1, $this->y + $dy));
        } else {
            // Gradient ascent towards highest attractant
            $target = $grid->getHighestGradient($this->x, $this->y, PheromoneType::ATTRACTANT);
            $newX = $target['x'];
            $newY = $target['y'];
        }

        $this->x = $newX;
        $this->y = $newY;

        // Deposit trail pheromone
        $grid->deposit($this->x, $this->y, PheromoneType::TRAIL, 2.0);

        return ['x' => $this->x, 'y' => $this->y];
    }

    /**
     * Evaluate Call for Proposals and generate competitive auction bid.
     */
    public function createBid(TaskProposal $proposal): ?AgentBid
    {
        if ($this->capacity < 0.2) {
            return null; // Busy, cannot accept more tasks
        }

        // Calculate bid score based on processing power, capacity, and spatial distance
        $dist = sqrt(($this->x - $proposal->getOriginX()) ** 2 + ($this->y - $proposal->getOriginY()) ** 2);
        $cost = round(($proposal->getEstimatedEffort() / $this->processingPower) + ($dist * 0.1), 3);
        $bidScore = round(($this->reputation * $this->capacity) / max(0.1, $cost), 4);

        return new AgentBid(
            agentId: $this->id,
            taskId: $proposal->getId(),
            bidCost: $cost,
            bidScore: $bidScore,
            estimatedTimeMs: (float)($cost * 10.0)
        );
    }

    /**
     * Execute awarded task proposal.
     */
    public function executeTask(TaskProposal $proposal): array
    {
        $this->capacity = max(0.0, $this->capacity - 0.5);
        $this->currentTaskId = $proposal->getId();

        // Simulate execution
        $result = ($proposal->getPayload()['handler'] ?? null);
        $output = is_callable($result) ? $result($proposal) : ['status' => 'success', 'task' => $proposal->getId()];

        $this->tasksCompleted++;
        $this->capacity = 1.0;
        $this->currentTaskId = null;
        $this->reputation = min(2.0, $this->reputation + 0.05);

        return [
            'agent_id' => $this->id,
            'task_id'  => $proposal->getId(),
            'output'   => $output,
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRole(): AgentRole
    {
        return $this->role;
    }

    public function setRole(AgentRole $role): void
    {
        $this->role = $role;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function moveTo(int $x, int $y): void
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function getCapacity(): float
    {
        return $this->capacity;
    }

    public function getReputation(): float
    {
        return $this->reputation;
    }

    public function getTasksCompleted(): int
    {
        return $this->tasksCompleted;
    }
}
