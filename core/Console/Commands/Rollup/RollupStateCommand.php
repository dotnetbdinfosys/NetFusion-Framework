<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup CLI
 *
 * Command to inspect state tree leaves, balances, and global state root.
 *
 * @package    NetFusion\Core\Console\Commands\Rollup
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands\Rollup;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Rollup\RollupManager;

class RollupStateCommand extends Command
{
    protected string $name = 'rollup:state';

    protected string $signature = 'rollup:state {--account= : Specific account ID to inspect} {--all : Display all registered accounts in SMT state}';


    protected string $description = 'Inspect account balances, nonces, and current SMT state roots';

    public function handle(): int
    {
        $manager = rollup();
        $accountId = $this->option('account');


        if ($accountId) {
            $account = $manager->getAccount((string) $accountId);
            if ($account === null) {
                $this->error("Account [{$accountId}] does not exist in state tree.");
                return 1;
            }

            $this->info("=== Rollup Account State ===");
            $this->line(sprintf("Account ID   : %s", $account->getAccountId()));
            $this->line(sprintf("Public Key   : %s", $account->getPublicKey()));
            $this->line(sprintf("Balance      : %.4f", $account->getBalance()));
            $this->line(sprintf("Nonce        : %d", $account->getNonce()));
            $this->line(sprintf("State Hash   : %s", $account->getHash()));
            return 0;
        }

        // List all accounts or demo seed
        $accounts = $manager->getAccounts();
        if (empty($accounts)) {
            $manager->registerAccount('treasury', null, 10000.0);
            $manager->registerAccount('alice', null, 500.0);
            $accounts = $manager->getAccounts();
        }

        $this->info("=== Global SMT State Overview ===");
        $this->line(sprintf("Current State Root : %s", $manager->getStateRoot()));
        $this->line(sprintf("MMR History Root   : %s", $manager->getMmrRoot()));
        $this->line(sprintf("Total Accounts     : %d", count($accounts)));
        $this->line("---------------------------------------------------------------");
        $this->line(sprintf("%-16s | %-12s | %-6s | %s", "Account ID", "Balance", "Nonce", "State Hash (first 16)"));
        $this->line("---------------------------------------------------------------");

        foreach ($accounts as $acc) {
            $this->line(sprintf(
                "%-16s | %-12.2f | %-6d | %s...",
                $acc->getAccountId(),
                $acc->getBalance(),
                $acc->getNonce(),
                substr($acc->getHash(), 0, 16)
            ));
        }

        return 0;
    }
}
