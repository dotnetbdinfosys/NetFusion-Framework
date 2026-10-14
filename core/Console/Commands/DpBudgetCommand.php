<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Console/Commands/DpBudgetCommand.php
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

class DpBudgetCommand extends Command
{
    protected string $signature = 'dp:budget {identifier? : Budget identifier} {--reset : Reset consumed budget}';
    protected string $description = 'Inspect or reset privacy budget consumption for an analyst/tenant';

    public function handle(): int
    {
        $id = (string)($this->argument(0) ?: 'default_analyst');
        $reset = $this->hasOption('reset');

        $mgr = $this->app->has('diff_privacy')
            ? $this->app->make('diff_privacy')
            : DifferentialPrivacyManager::getInstance();

        $budget = $mgr->budget($id);

        if ($reset) {
            $budget->reset();
            $this->info("Privacy budget for '{$id}' successfully reset.");
            return 0;
        }

        $this->info("=== Privacy Budget Status: {$id} ===");
        $this->line("Total Epsilon     : " . $budget->getTotalEpsilon());
        $this->line("Consumed Epsilon  : " . round($budget->getConsumedEpsilon(), 4));
        $this->line("Remaining Epsilon : " . round($budget->getRemainingEpsilon(), 4));
        $this->line("Status            : " . strtoupper($budget->getStatus()->value));
        $this->line("History Entries   : " . count($budget->getHistory()));

        return 0;
    }
}
