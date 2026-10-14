<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/SwarmColony.php
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

namespace NetFusion\Core\AI\Swarm;

use NetFusion\Core\AI\Swarm\Agents\SwarmAgent;
use NetFusion\Core\AI\Swarm\Enums\PheromoneType;
use NetFusion\Core\AI\Swarm\Pheromone\PheromoneGrid;
use NetFusion\Core\AI\Swarm\Pheromone\DiffusionEngine;
use NetFusion\Core\AI\Swarm\Auction\ContractNetProtocol;
use NetFusion\Core\AI\Swarm\Auction\TaskProposal;
use NetFusion\Core\AI\Swarm\Consensus\BftSwarmCoordinator;

/**
 * Autonomous Swarm Colony Container.
 *
 * Encapsulates spatial stigmergic grid, dynamic agent population,
 * auction marketplace, and BFT consensus coordinator.
 */
class SwarmColony
{
    private readonly PheromoneGrid $grid;
    private readonly DiffusionEngine $diffusion;
    private readonly ContractNetProtocol $cnp;
    private readonly BftSwarmCoordinator $bft;

    /** @var array<string, SwarmAgent> */
    private array $agents = [];

    public function __construct(
        private readonly string $id,
        int $gridWidth = 20,
        int $gridHeight = 20
    ) {
        $this->grid = new PheromoneGrid($gridWidth, $gridHeight);
        $this->diffusion = new DiffusionEngine();
        $this->cnp = new ContractNetProtocol();
        $this->bft = new BftSwarmCoordinator();
    }

    public function addAgent(SwarmAgent $agent): self
    {
        $this->agents[$agent->getId()] = $agent;
        return $this;
    }

    public function getAgent(string $id): ?SwarmAgent
    {
        return $this->agents[$id] ?? null;
    }

    /**
     * @return array<string, SwarmAgent>
     */
    public function getAgents(): array
    {
        return $this->agents;
    }

    public function getAgentCount(): int
    {
        return count($this->agents);
    }

    /**
     * Advance colony simulation by 1 stigmergic step.
     */
    public function step(float $evaporationRate = 0.05, float $diffusionRate = 0.1): array
    {
        // 1. Move all agents stigmergically
        foreach ($this->agents as $agent) {
            $agent->stepStigmergic($this->grid);
        }

        // 2. Spatial diffusion of attractants and trails
        $this->diffusion->diffuse($this->grid, PheromoneType::ATTRACTANT, $diffusionRate);
        $this->diffusion->diffuse($this->grid, PheromoneType::TRAIL, $diffusionRate);

        // 3. Environmental evaporation
        $this->grid->evaporate($evaporationRate);

        return [
            'colony_id'       => $this->id,
            'total_agents'    => count($this->agents),
            'attractant_mass' => $this->grid->getTotalConcentration(PheromoneType::ATTRACTANT),
            'trail_mass'      => $this->grid->getTotalConcentration(PheromoneType::TRAIL),
        ];
    }

    /**
     * Conduct decentralized task auction via Contract Net Protocol.
     */
    public function auctionTask(TaskProposal $proposal): array
    {
        return $this->cnp->conductAuction($proposal, array_values($this->agents));
    }

    /**
     * Reach Byzantine Quorum Consensus on arbitrary colony state transition.
     *
     * @param array<string, mixed> $stateData
     * @param string[] $voterIds
     */
    public function certifyState(string $stateId, array $stateData, array $voterIds): array
    {
        $hash = $this->bft->hashProposal($stateId, $stateData);
        $votes = [];

        foreach ($voterIds as $vId) {
            $votes[] = $this->bft->createVote($vId, $hash);
        }

        return $this->bft->certifyQuorum($hash, $votes, count($voterIds));
    }

    public function depositPheromone(int $x, int $y, PheromoneType $type, float $amount): void
    {
        $this->grid->deposit($x, $y, $type, $amount);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGrid(): PheromoneGrid
    {
        return $this->grid;
    }

    public function getBft(): BftSwarmCoordinator
    {
        return $this->bft;
    }
}
