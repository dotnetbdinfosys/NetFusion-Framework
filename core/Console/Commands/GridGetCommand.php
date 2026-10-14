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

class GridGetCommand extends Command
{
    protected string $name = 'grid:get';
    protected string $signature = 'grid:get {map : Distributed map name} {key : Key to look up} {--cluster= : Cluster name}';
    protected string $description = 'Retrieve a key from an In-Memory Data Grid map with tier breakdown';

    public function handle(): int
    {
        /** @var IMDGManager $manager */
        $manager = function_exists('imdg') ? imdg() : (function_exists('app') && app()->has('imdg') ? app('imdg') : new IMDGManager());

        $mapName = (string) $this->argument(0);
        $key = (string) $this->argument(1);
        $clusterName = $this->option('cluster') ? (string) $this->option('cluster') : null;

        if ($mapName === '' || $key === '') {
            $this->error("Usage: php netfusion grid:get <map> <key>");
            return 1;
        }

        $cluster = $manager->cluster($clusterName);
        $map = $cluster->getMap($mapName);

        $startTime = microtime(true);
        $value = $map->get($key);
        $latencyMs = round((microtime(true) - $startTime) * 1000, 3);

        $partitionId = $cluster->getPartitionMap()->getPartitionId($key);
        $partition = $cluster->getPartitionMap()->getPartition($partitionId);

        $this->info("==========================================================================================");
        $this->info("                 IMDG Key Lookup: [{$mapName}] -> '{$key}'");
        $this->info("==========================================================================================");
        $this->line("Partition ID     : {$partitionId}");
        $this->line("Primary Owner    : " . ($partition?->getPrimaryOwnerId() ?? 'N/A'));
        $this->line("Lookup Latency   : {$latencyMs} ms");
        $this->line("------------------------------------------------------------------------------------------");

        if ($value === null) {
            $this->line("\033[33m(nil) — Key not found in map.\033[0m");
            return 0;
        }

        $this->info("Value:");
        if (is_array($value) || is_object($value)) {
            $this->line(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line((string) $value);
        }

        return 0;
    }
}
