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
use NetFusion\Core\IMDG\IMDGManager;

class GridNodesCommand extends Command
{
    protected string $name = 'grid:nodes';
    protected string $signature = 'grid:nodes {--cluster= : Cluster name}';
    protected string $description = 'List all member nodes, partition distribution, and cluster topology in the IMDG';

    public function handle(): int
    {
        /** @var IMDGManager $manager */
        $manager = function_exists('imdg') ? imdg() : (function_exists('app') && app()->has('imdg') ? app('imdg') : new IMDGManager());

        $clusterName = $this->option('cluster') ? (string) $this->option('cluster') : null;
        $cluster = $manager->cluster($clusterName);
        $members = $cluster->getMembers();

        $this->info("==========================================================================================");
        $this->info("                     IMDG Cluster Topology: {$cluster->getClusterName()}");
        $this->info("==========================================================================================");
        $this->line("Total Partitions : {$cluster->getPartitionMap()->getPartitionCount()}");
        $this->line("Local Node ID    : {$cluster->getLocalNode()->getId()} ({$cluster->getLocalNode()->getAddress()})");
        $this->line("Total Members    : " . count($members));
        $this->line("------------------------------------------------------------------------------------------");

        if (empty($members)) {
            $this->line("No active cluster member nodes found.");
            return 0;
        }

        $headers = ['Node ID', 'Address', 'Weight', 'Local', 'Status', 'Last Heartbeat'];
        $rows = [];

        foreach ($members as $node) {
            $ago = round(microtime(true) - $node->getLastHeartbeat(), 2);
            $rows[] = [
                $node->getId(),
                $node->getAddress(),
                (string) $node->getWeight(),
                $node->isLocal() ? 'YES' : 'NO',
                $node->getStatus(),
                "{$ago}s ago",
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }
}
