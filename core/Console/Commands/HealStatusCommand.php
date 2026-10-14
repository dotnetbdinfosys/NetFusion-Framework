<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/HealStatusCommand.php
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
use NetFusion\Core\Infrastructure\SelfHealing\SelfHealingManager;

class HealStatusCommand extends Command
{
    protected string $signature = 'heal:status';
    protected string $description = 'Display autonomous self-healing infrastructure status and active incidents';

    public function handle(): int
    {
        $mgr = $this->app->has('selfhealing')
            ? $this->app->make('selfhealing')
            : SelfHealingManager::getInstance();

        $orchestrator = $mgr->getOrchestrator();

        $this->info("=== Autonomous Self-Healing Infrastructure Status ===");
        $this->line("Engine Status      : ACTIVE");
        $this->line("Active Anomalies   : " . count($orchestrator->getActiveAnomalies()));
        $this->line("Total RCA Reports  : " . count($orchestrator->getReports()));
        $this->line("Remediation Actions: " . count($orchestrator->getActions()));
        $this->line("Blast Radius Guard : " . $orchestrator->getBlastRadiusPolicy()->getMaxDisruptedPercent() . "% max disruption");

        return 0;
    }
}
