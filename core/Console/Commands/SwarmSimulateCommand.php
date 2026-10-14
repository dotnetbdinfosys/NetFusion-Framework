<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/SwarmSimulateCommand.php
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

class SwarmSimulateCommand extends Command
{
    protected string $signature = 'swarm:simulate {colony? : Colony ID} {--steps=5 : Stigmergic simulation steps}';
    protected string $description = 'Simulate multi-agent stigmergic movement, diffusion, and pheromone decay';

    public function handle(): int
    {
        $colonyId = (string)($this->argument(0) ?: 'colony_alpha');
        $steps    = (int)($this->option('steps') ?: 5);

        $mgr = $this->app->has('swarm')
            ? $this->app->make('swarm')
            : SwarmManager::getInstance();

        $res = $mgr->simulate($colonyId, $steps);

        $this->info("=== Multi-Agent Stigmergy Simulation Completed ===");
        $this->line("Colony Identifier    : " . $colonyId);
        $this->line("Steps Executed       : " . $res['steps_executed']);
        $this->line("Final Attractant Mass: " . $res['final_attractant_mass']);
        $this->line("Final Trail Mass     : " . $res['final_trail_mass']);

        return 0;
    }
}
