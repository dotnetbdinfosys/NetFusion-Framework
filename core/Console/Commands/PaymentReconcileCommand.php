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
use NetFusion\Core\Payment\PaymentOrchestrator;

class PaymentReconcileCommand extends Command
{
    protected string $signature = 'payment:reconcile {processor : Payment processor name (e.g. stripe, bkash)}';
    protected string $description = 'Reconcile settlement statements against internal payment transactions';

    public function handle(): int
    {
        $processor = (string)($this->argument(0) ?? $this->option('processor', 'stripe'));

        $this->info("========================================================================");
        $this->info("  NetFusion Framework — Settlement Statement Reconciliation Summary [{$processor}]");
        $this->info("========================================================================");

        /** @var PaymentOrchestrator|null $orchestrator */
        $orchestrator = $this->app->has('payment') ? $this->app->make('payment') : null;

        if ($orchestrator === null) {
            $this->warn("Payment orchestrator not registered.");
            return 1;
        }

        $reconciler = $orchestrator->getReconciliation();
        $res = $reconciler->reconcile($processor, []);

        $this->line(sprintf("  • Matched Transactions   : %d", $res['matched']));
        $this->line(sprintf("  • Unmatched Transactions : %d", $res['unmatched']));
        $this->line(sprintf("  • Net Fee Variance       : %0.2f", $res['fee_variance']));
        $this->info("========================================================================");

        return 0;
    }
}
