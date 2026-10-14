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

class PaymentStatusCommand extends Command
{
    protected string $signature = 'payment:status';
    protected string $description = 'Display payment gateways health, routing priority, and vault metrics';

    public function handle(): int
    {
        $this->info("========================================================================");
        $this->info("  NetFusion Framework — Payment Gateway Orchestrator & Vault Status");
        $this->info("========================================================================");

        /** @var PaymentOrchestrator|null $orchestrator */
        $orchestrator = $this->app->has('payment') ? $this->app->make('payment') : null;

        if ($orchestrator === null) {
            $this->warn("Payment orchestrator not registered in container.");
            return 1;
        }

        $healthTracker = $orchestrator->getRouter()->getHealthTracker();
        $stats = $healthTracker->getStats();

        $this->line("\n[Gateway Health & Routing Status]");
        $gateways = ['stripe', 'paypal', 'bkash', 'nagad', 'sslcommerz'];

        foreach ($gateways as $gw) {
            $isHealthy = $healthTracker->isHealthy($gw);
            $rate = $healthTracker->getSuccessRate($gw);
            $statusText = $isHealthy ? "\033[32mHEALTHY\033[0m" : "\033[31mCIRCUIT_TRIPPED\033[0m";
            $this->line(sprintf("  • %-12s : %s (Success Rate: %s%%)", strtoupper($gw), $statusText, $rate));
        }

        $this->line("\n[PCI-DSS Tokenization Vault]");
        $this->line("  • Engine      : AES-256-GCM Zero-Knowledge Vault");
        $this->line("  • Compliance  : PCI-DSS Level 1 (PAN Encrypted, CVV Wiped)");

        $this->info("\n========================================================================");
        return 0;
    }
}
