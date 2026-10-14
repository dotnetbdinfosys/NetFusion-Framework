<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup CLI
 *
 * Command to verify cryptographic proofs (SMT state inclusion and MMR history).
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

class RollupVerifyCommand extends Command
{
    protected string $name = 'rollup:verify';

    protected string $signature = 'rollup:verify {--account=alice : Account ID to verify SMT state tree inclusion} {--batch=1 : Batch ID to verify MMR history inclusion}';


    protected string $description = 'Cryptographically verify Sparse Merkle Tree state and MMR historical proofs';

    public function handle(): int
    {
        $manager = rollup();
        $accountId = (string) ($this->option('account') ?: 'alice');
        $batchId   = (int) ($this->option('batch') ?: 1);


        // Ensure baseline account exists for verification demo
        if ($manager->getAccount($accountId) === null) {
            $manager->registerAccount($accountId, null, 750.0);
        }

        $this->info("=== Cryptographic Rollup Proof Verification ===");

        // 1. SMT Account Inclusion
        $accountValid = $manager->verifyAccountInclusion($accountId);
        $accountLeaf  = $manager->getAccount($accountId);
        $this->line(sprintf("Account ID   : %s", $accountId));
        $this->line(sprintf("Balance      : %.2f | Nonce: %d", $accountLeaf?->getBalance() ?? 0.0, $accountLeaf?->getNonce() ?? 0));
        $this->line(sprintf("SMT State Root: %s", $manager->getStateRoot()));
        $this->line(sprintf("SMT Inclusion: %s", $accountValid ? "\033[32mVALID [OK]\033[0m" : "\033[31mINVALID [FAIL]\033[0m"));

        // 2. MMR Batch Inclusion
        if (empty($manager->getBatches())) {
            $manager->mint($accountId, 100.0);
            $manager->processBatch();
        }

        $batches = $manager->getBatches();
        if (isset($batches[$batchId])) {
            $targetBatch = $batches[$batchId];
            $mmrValid = $manager->verifyBatchInMmr($batchId - 1, $targetBatch->getBatchHash());
            $this->line(sprintf("Batch #%d Hash: %s", $batchId, $targetBatch->getBatchHash()));
            $this->line(sprintf("MMR History  : %s", $mmrValid ? "\033[32mVALID [OK]\033[0m" : "\033[31mINVALID [FAIL]\033[0m"));
        } else {
            $this->line("Batch #{$batchId} not found in MMR accumulator.");
        }

        return 0;
    }
}
