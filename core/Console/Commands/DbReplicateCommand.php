<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
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
use NetFusion\Core\DistributedDb\DistributedDbManager;
use NetFusion\Core\DistributedDb\MultiMaster\GeoRegionNode;

/**
 * Class DbReplicateCommand
 *
 * CLI: php netfusion db:replicate [--sync] [--source=us-east-1] [--target=eu-west-1]
 */
class DbReplicateCommand extends Command
{
    protected string $name = 'db:replicate';
    protected string $description = 'Display multi-master geo-replication queues, replication lag, and trigger synchronization';

    public function handle(array $args = []): int
    {
        $doSync = false;
        $source = null;
        $target = null;

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if ($arg === '--sync') {
                $doSync = true;
            } elseif (str_starts_with($arg, '--source=')) {
                $source = substr($arg, 9);
            } elseif (str_starts_with($arg, '--target=')) {
                $target = substr($arg, 9);
            }
        }

        /** @var DistributedDbManager $dbManager */
        $dbManager = app(DistributedDbManager::class);
        $coordinator = $dbManager->multiMaster();

        // Ensure default regions exist for CLI inspection if empty
        if (empty($coordinator->getRegions())) {
            $coordinator->addRegion(new GeoRegionNode('us-east-1'));
            $coordinator->addRegion(new GeoRegionNode('eu-west-1'));
            $coordinator->addRegion(new GeoRegionNode('ap-southeast-1'));
        }

        if ($doSync) {
            $synced = $coordinator->synchronize($source, $target);
            $this->info("Successfully synchronized {$synced} replication mutation events.");
        }

        $this->info("=== Multi-Master Geo-Replication Status ===");
        $this->line("Mode              : " . $coordinator->getReplicationMode()->value);
        $this->line("Conflict Strategy : " . $coordinator->getConflictStrategy()->value);
        $this->line("Quorum Threshold  : " . $coordinator->getQuorum());
        $this->line("--------------------------------------------------");

        $lag = $coordinator->getReplicationLag();
        foreach ($lag as $regionId => $info) {
            $vc = json_encode($info['vector_clock']);
            $this->line("Region: {$regionId} | Pending Outbox: {$info['pending_outbox']} | VectorClock: {$vc}");
        }

        $conflicts = $coordinator->getAllConflictHistory();
        $this->line("Total Conflicts Reconciled: " . count($conflicts));

        return 0;
    }
}
