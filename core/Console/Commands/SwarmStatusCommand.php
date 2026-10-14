<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/SwarmStatusCommand.php
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\AI\Swarm\SwarmManager;

class SwarmStatusCommand extends Command
{
    protected string $signature = 'swarm:status {colony? : Swarm colony identifier}';
    protected string $description = 'Display autonomous swarm colony population, pheromone density, and consensus health';

    public function handle(): int
    {
        $colonyId = (string)($this->argument(0) ?: 'colony_alpha');

        $mgr = $this->app->has('swarm')
            ? $this->app->make('swarm')
            : SwarmManager::getInstance();

        $colony = $mgr->getColony($colonyId);
        if ($colony === null) {
            $colony = $mgr->createColony($colonyId);
        }

        $this->info("=== Autonomous Swarm Colony Status ===");
        $this->line("Colony Identifier : " . $colony->getId());
        $this->line("Grid Dimensions   : " . $colony->getGrid()->getWidth() . "x" . $colony->getGrid()->getHeight());
        $this->line("Agent Population  : " . $colony->getAgentCount());
        $this->line("Attractant Mass   : " . $colony->getGrid()->getTotalConcentration(\NetFusion\Core\AI\Swarm\Enums\PheromoneType::ATTRACTANT));
        $this->line("Trail Mass        : " . $colony->getGrid()->getTotalConcentration(\NetFusion\Core\AI\Swarm\Enums\PheromoneType::TRAIL));
        $this->line("Consensus Engine  : BFT-Swarm Active");

        return 0;
    }
}
