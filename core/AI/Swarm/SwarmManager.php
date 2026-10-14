<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/SwarmManager.php
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
use NetFusion\Core\AI\Swarm\Enums\AgentRole;
use NetFusion\Core\AI\Swarm\Enums\PheromoneType;
use NetFusion\Core\AI\Swarm\Auction\TaskProposal;
use NetFusion\Core\AI\Swarm\Exceptions\SwarmException;

/**
 * Enterprise Autonomous Swarm Intelligence Orchestrator.
 */
class SwarmManager
{
    private static ?self $instance = null;

    /** @var array<string, SwarmColony> */
    private array $colonies = [];

    public function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Create and register a new swarm colony.
     */
    public function createColony(string $id, int $width = 20, int $height = 20): SwarmColony
    {
        $colony = new SwarmColony($id, $width, $height);
        $this->colonies[$id] = $colony;
        return $colony;
    }

    public function getColony(string $id): ?SwarmColony
    {
        return $this->colonies[$id] ?? null;
    }

    /**
     * @return array<string, SwarmColony>
     */
    public function getColonies(): array
    {
        return $this->colonies;
    }

    /**
     * Execute distributed task auction on a colony.
     */
    public function auction(string $colonyId, TaskProposal $proposal): array
    {
        $colony = $this->getColony($colonyId);
        if ($colony === null) {
            // Auto-provision standard colony with 5 default worker agents
            $colony = $this->createColony($colonyId);
            for ($i = 0; $i < 5; $i++) {
                $colony->addAgent(new SwarmAgent("agent_{$i}", AgentRole::WORKER, mt_rand(0, 19), mt_rand(0, 19)));
            }
        }

        return $colony->auctionTask($proposal);
    }

    /**
     * Deposit environmental pheromone into a colony grid.
     */
    public function deposit(string $colonyId, int $x, int $y, PheromoneType $type, float $amount): void
    {
        $colony = $this->getColony($colonyId);
        if ($colony === null) {
            $colony = $this->createColony($colonyId);
        }

        $colony->depositPheromone($x, $y, $type, $amount);
    }

    /**
     * Simulate multi-agent stigmergic movement and environmental evolution over discrete steps.
     *
     * @return array{steps_executed: int, final_attractant_mass: float, final_trail_mass: float}
     */
    public function simulate(string $colonyId, int $steps = 5): array
    {
        $colony = $this->getColony($colonyId);
        if ($colony === null) {
            $colony = $this->createColony($colonyId);
            for ($i = 0; $i < 6; $i++) {
                $colony->addAgent(new SwarmAgent("sim_agent_{$i}", AgentRole::WORKER, mt_rand(0, 19), mt_rand(0, 19)));
            }
            $colony->depositPheromone(10, 10, PheromoneType::ATTRACTANT, 50.0);
        }

        $lastStep = [];
        for ($s = 0; $s < $steps; $s++) {
            $lastStep = $colony->step();
        }

        return [
            'steps_executed'        => $steps,
            'final_attractant_mass' => $lastStep['attractant_mass'] ?? 0.0,
            'final_trail_mass'      => $lastStep['trail_mass'] ?? 0.0,
        ];
    }

    /**
     * Benchmark swarm auction throughput and stigmergic iterations per second.
     *
     * @return array{
     *   agents: int,
     *   tasks_auctioned: int,
     *   elapsed_ms: float,
     *   auctions_per_sec: float,
     *   total_bids_processed: int
     * }
     */
    public function benchmark(int $agentCount = 20, int $taskCount = 10): array
    {
        $colony = new SwarmColony('bench_colony', 30, 30);
        for ($i = 0; $i < $agentCount; $i++) {
            $colony->addAgent(new SwarmAgent("bench_agent_{$i}", AgentRole::WORKER, mt_rand(0, 29), mt_rand(0, 29)));
        }

        $start = microtime(true);
        $totalBids = 0;

        for ($t = 0; $t < $taskCount; $t++) {
            $proposal = new TaskProposal("task_{$t}", 'compute', estimatedEffort: (float)mt_rand(1, 5));
            $res = $colony->auctionTask($proposal);
            $totalBids += $res['total_bids'];
        }

        $elapsed = max(0.0001, microtime(true) - $start);
        $rate = round($taskCount / $elapsed, 2);

        return [
            'agents'               => $agentCount,
            'tasks_auctioned'      => $taskCount,
            'elapsed_ms'           => round($elapsed * 1000.0, 2),
            'auctions_per_sec'     => $rate,
            'total_bids_processed' => $totalBids,
        ];
    }
}
