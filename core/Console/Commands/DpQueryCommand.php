<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/DpQueryCommand.php
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

class DpQueryCommand extends Command
{
    protected string $signature = 'dp:query {type? : Query type: count, sum, mean} {--val= : True value or count} {--eps= : Epsilon budget} {--budget= : Budget identifier}';
    protected string $description = 'Execute a differentially private aggregation query with calibrated noise';

    public function handle(): int
    {
        $type   = strtolower((string)($this->argument(0) ?: 'count'));
        $val    = (float)($this->option('val') ?: 100);
        $eps    = (float)($this->option('eps') ?: 1.0);
        $budget = (string)($this->option('budget') ?: 'default_analyst');

        $mgr = $this->app->has('diff_privacy')
            ? $this->app->make('diff_privacy')
            : DifferentialPrivacyManager::getInstance();

        $result = match ($type) {
            'sum'   => $mgr->sum([$val], 0.0, $val * 2, $eps, $budget),
            'mean'  => $mgr->mean([$val, $val * 1.5], 0.0, $val * 2, $eps, $budget),
            default => $mgr->count($val, $eps, $budget),
        };

        $this->info("=== Differentially Private Query Result ===");
        $this->line("Query Type : " . strtoupper($type));
        $this->line("True Value : {$val}");
        $this->line("Noisy DP   : {$result}");
        $this->line("Epsilon (ε): {$eps}");
        $this->line("Budget ID  : {$budget}");

        return 0;
    }
}
