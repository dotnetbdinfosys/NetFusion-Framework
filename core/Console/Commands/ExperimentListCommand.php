<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Experimentation\ExperimentManager;

class ExperimentListCommand extends Command
{
    protected string $signature = 'experiment:list';
    protected string $description = 'List all registered multivariate experiments, status, and variants';

    public function handle(): int
    {
        /** @var ExperimentManager $manager */
        $manager = function_exists('app') && app()->has('experiment') ? app('experiment') : new ExperimentManager();

        $experiments = $manager->all();

        $this->info("=========================================================");
        $this->info("         NetFusion Multivariate Experiments              ");
        $this->info("=========================================================");

        if (empty($experiments)) {
            $this->line("No experiments found.");
            return 0;
        }

        foreach ($experiments as $exp) {
            $statusColor = match ($exp->status) {
                'running' => "\033[32mRUNNING\033[0m",
                'paused' => "\033[33mPAUSED\033[0m",
                'concluded' => "\033[36mCONCLUDED\033[0m",
                default => "\033[37mDRAFT\033[0m",
            };

            $this->line("Experiment : \033[1;37m{$exp->key}\033[0m ({$exp->name})");
            $this->line("Status     : {$statusColor} | Traffic: {$exp->trafficAllocation}%");
            if ($exp->winnerVariant) {
                $this->line("Winner     : \033[32m{$exp->winnerVariant}\033[0m");
            }
            $variantList = array_map(fn($v) => "{$v->name} ({$v->weight}%)", $exp->variants);
            $this->line("Variants   : " . implode(', ', $variantList));
            $this->line("Goals      : " . (!empty($exp->goals) ? implode(', ', $exp->goals) : 'None'));
            $this->line("---------------------------------------------------------");
        }

        return 0;
    }
}
