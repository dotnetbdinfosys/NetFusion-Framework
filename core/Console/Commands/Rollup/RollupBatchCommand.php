<?php
/**
 * NetFusion Framework - Enterprise Zero-Knowledge Rollup CLI
 *
 * Command to process pending transitions and commit a new Rollup batch.
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

class RollupBatchCommand extends Command
{
    protected string $name = 'rollup:batch';

    protected string $signature = 'rollup:batch {--max=50 : Maximum number of transitions to bundle in the batch}';


    protected string $description = 'Process pending transactions and seal a committed ZK-Rollup batch';

    public function handle(): int
    {
        $manager = rollup();
        $max = (int) ($this->option('max') ?: 50);



        // If mempool is empty, seed demo mint and transfer transitions for demonstration
        if (empty($manager->getPendingTransitions())) {
            $this->info("Initializing sample rollup accounts and mempool transitions...");
            $manager->registerAccount('alice', null, 500.0);
            $manager->registerAccount('bob', null, 100.0);
            $manager->mint('alice', 200.0);
            $manager->transfer('alice', 'bob', 150.0);
            $manager->transfer('bob', 'alice', 50.0);
        }

        $batch = $manager->processBatch($max);

        if ($batch === null) {
            $this->warn("No pending transitions found to process.");
            return 0;
        }

        $this->info("=== ZK-Rollup Batch Committed ===");
        $this->line(sprintf("Batch ID        : #%d", $batch->getBatchId()));
        $this->line(sprintf("Transitions     : %d", $batch->getTransitionsCount()));
        $this->line(sprintf("Prev State Root : %s", $batch->getPreviousStateRoot()));
        $this->line(sprintf("New State Root  : %s", $batch->getNewStateRoot()));
        $this->line(sprintf("Batch Hash      : %s", $batch->getBatchHash()));
        $this->line(sprintf("MMR Root        : %s", $manager->getMmrRoot()));
        $this->line(sprintf("Proof Scheme    : %s", $batch->getValidityProof()['proof_scheme'] ?? 'N/A'));
        $this->line(sprintf("Status          : %s", $batch->getStatus()->value));

        return 0;
    }
}
