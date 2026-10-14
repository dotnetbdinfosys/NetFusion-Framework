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
use NetFusion\Core\Data\Replication\ReplicationManager;

class ReplicationSyncCommand extends Command
{
    protected string $name = 'replication:sync';
    protected string $description = 'Synchronize pending edge change deltas with remote regions';

    public function handle(): int
    {
        /** @var ReplicationManager $manager */
        $manager = $this->app->has('replication') ? $this->app->make('replication') : new ReplicationManager();

        $target = $this->option('region') ?? $this->argument(0);

        if ($target) {
            $this->info("Synchronizing with target region [{$target}]...");
            try {
                $res = $manager->syncWith($target);
                $this->info("Sync completed with [{$target}]: {$res['synced']} delta event(s) applied.");
            } catch (\Throwable $e) {
                $this->error("Sync failed: " . $e->getMessage());
                return 1;
            }
        } else {
            $this->info("Broadcasting delta sync to all edge cluster nodes...");
            $results = $manager->syncAll();
            foreach ($results as $regionId => $res) {
                $this->line("  Region [{$regionId}]: {$res['synced']} synced ({$res['status']})");
            }
            $this->info("All active regions synchronized successfully.");
        }

        return 0;
    }
}
