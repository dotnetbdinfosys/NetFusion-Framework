<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/HealRemediateCommand.php
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
use NetFusion\Core\Infrastructure\SelfHealing\Analysis\RootCauseReport;
use NetFusion\Core\Infrastructure\SelfHealing\Enums\ActionType;
use NetFusion\Core\Infrastructure\SelfHealing\Enums\IncidentSeverity;
use NetFusion\Core\Infrastructure\SelfHealing\SelfHealingManager;

class HealRemediateCommand extends Command
{
    protected string $signature = 'heal:remediate {service? : Service to remediate} {--action=restart_pod : Action type} {--canary=10 : Initial canary percentage}';
    protected string $description = 'Trigger progressive canary remediation on an anomalous service';

    public function handle(): int
    {
        $service = (string)($this->argument(0) ?: 'payment-service');
        $actionName = strtolower((string)($this->option('action') ?: 'restart_pod'));

        $actionType = ActionType::tryFrom($actionName) ?? ActionType::RESTART_POD;

        $mgr = $this->app->has('selfhealing')
            ? $this->app->make('selfhealing')
            : SelfHealingManager::getInstance();

        $dummyReport = new RootCauseReport(
            'cli_rca_' . substr(md5((string)microtime(true)), 0, 6),
            $service,
            'MEMORY_LEAK',
            0.85,
            [$service],
            ['MEMORY_LEAK' => 0.85],
            $actionType,
            IncidentSeverity::HIGH
        );

        $execution = $mgr->remediate($dummyReport, fn() => true, 10);

        $this->info("=== Canary Remediation Completed ===");
        $this->line("Action ID       : " . $execution->getId());
        $this->line("Target Service  : " . $execution->getTargetService());
        $this->line("Action Type     : " . strtoupper($execution->getActionType()->value));
        $this->line("Rollout Status  : " . strtoupper($execution->getStatus()->value));
        $this->line("Final Canary %  : " . $execution->getCanaryPercentage() . "%");
        $this->line("Execution Steps : " . count($execution->getLogs()));

        return 0;
    }
}
