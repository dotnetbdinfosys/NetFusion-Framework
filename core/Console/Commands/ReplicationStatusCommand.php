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

class ReplicationStatusCommand extends Command
{
    protected string $name = 'replication:status';
    protected string $description = 'Display multi-region edge cluster health, topology and vector clock state';

    public function handle(): int
    {
        /** @var ReplicationManager $manager */
        $manager = $this->app->has('replication') ? $this->app->make('replication') : new ReplicationManager();

        $localRegion = $manager->getLocalRegionId();
        $vClock = $manager->getLocalVectorClock();
        $store = $manager->getStore();
        $pending = count($store->getPending());

        $this->info("=== Multi-Region Edge Replication Cluster Status ===");
        $this->line("Local Region ID : <comment>{$localRegion}</comment>");
        $this->line("Local Vector Clock : <comment>{$vClock}</comment>");
        $this->line("Pending Outbound Deltas : <comment>{$pending}</comment>");
        $this->line("");

        $this->line(str_repeat('-', 80));
        $this->line(sprintf("%-12s | %-28s | %-8s | %-12s | %-8s", "Region ID", "Name", "Type", "Latency", "Health"));
        $this->line(str_repeat('-', 80));

        foreach ($manager->getNodes() as $node) {
            $type = $node->isLocal ? 'LOCAL' : 'REMOTE';
            $health = $node->healthy ? '<info>HEALTHY</info>' : '<error>DOWN</error>';
            $latency = $node->latencyMs > 0 ? sprintf('%.1fms', $node->latencyMs) : 'N/A';

            $this->line(sprintf(
                "%-12s | %-28s | %-8s | %-12s | %s",
                $node->id,
                substr($node->name, 0, 28),
                $type,
                $latency,
                $health
            ));
        }

        $this->line(str_repeat('-', 80));
        return 0;
    }
}
