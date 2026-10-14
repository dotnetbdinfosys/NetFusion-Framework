<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/DpFederateCommand.php
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
use NetFusion\Core\Privacy\Differential\DifferentialPrivacyManager;
use NetFusion\Core\Privacy\Differential\Federated\FederatedNodeUpdate;

class DpFederateCommand extends Command
{
    protected string $signature = 'dp:federate {--nodes=3 : Number of simulated federated nodes} {--eps=1.0 : Epsilon budget}';
    protected string $description = 'Simulate differentially private federated aggregation (DP-FedAvg)';

    public function handle(): int
    {
        $nodeCount = max(1, (int)($this->option('nodes') ?: 3));
        $eps       = (float)($this->option('eps') ?: 1.0);

        $mgr = $this->app->has('diff_privacy')
            ? $this->app->make('diff_privacy')
            : DifferentialPrivacyManager::getInstance();

        $fed = $mgr->getFederatedAggregator();

        $updates = [];
        for ($i = 1; $i <= $nodeCount; $i++) {
            $weights = [0.1 * $i, 0.2 * $i, -0.05 * $i];
            $updates[] = new FederatedNodeUpdate("node_{$i}", $weights, 100 * $i, clippingNorm: 1.0);
        }

        $aggregated = $fed->aggregate($updates, clippingNorm: 1.0, epsilon: $eps);

        $this->info("=== DP-FedAvg Federated Aggregation Completed ===");
        $this->line("Participating Nodes : {$nodeCount}");
        $this->line("Epsilon Allocated   : {$eps}");
        $this->line("Aggregated Weights  : " . json_encode($aggregated));

        return 0;
    }
}
