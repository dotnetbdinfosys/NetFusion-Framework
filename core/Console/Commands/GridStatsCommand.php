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

class GridStatsCommand extends Command
{
    protected string $name = 'grid:stats';
    protected string $signature = 'grid:stats {--cluster= : Cluster name}';
    protected string $description = 'Display comprehensive In-Memory Data Grid performance metrics and cache hit rates';

    public function handle(): int
    {
        /** @var IMDGManager $manager */
        $manager = function_exists('imdg') ? imdg() : (function_exists('app') && app()->has('imdg') ? app('imdg') : new IMDGManager());

        $clusterName = $this->option('cluster') ? (string) $this->option('cluster') : null;
        $stats = $manager->stats($clusterName);

        $this->info("==========================================================================================");
        $this->info("                 In-Memory Data Grid (IMDG) Performance Metrics");
        $this->info("==========================================================================================");
        $this->line("Cluster Name         : {$stats['clusterName']}");
        $this->line("Total Member Nodes   : {$stats['totalMembers']} (Alive: {$stats['aliveMembers']})");
        $this->line("Partitions Configured: {$stats['partitionCount']}");
        $this->line("Total Active Maps    : {$stats['totalMaps']}");
        $this->line("Total Key-Value Pairs: {$stats['totalEntries']}");
        $this->line("L1 Near-Cache Hits   : {$stats['l1NearCacheHits']}");
        $this->line("L2 Partition Hits    : {$stats['l2PartitionHits']}");
        $this->line("Cache Misses         : {$stats['misses']}");
        $this->line("Overall Hit Ratio    : {$stats['hitRatePercent']}%");
        $this->line("------------------------------------------------------------------------------------------");

        if (empty($stats['maps'])) {
            $this->line("No distributed maps currently initialized in memory.");
            return 0;
        }

        $headers = ['Map Name', 'Size', 'L1 Hits', 'L2 Hits', 'Misses', 'Hit Rate', 'Write Mode'];
        $rows = [];

        foreach ($stats['maps'] as $mapName => $m) {
            $rows[] = [
                $mapName,
                (string) $m['size'],
                (string) $m['l1NearCacheHits'],
                (string) $m['l2PartitionHits'],
                (string) $m['misses'],
                "{$m['hitRatePercent']}%",
                $m['writeMode'],
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }
}
