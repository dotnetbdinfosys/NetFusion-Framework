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

class GridPutCommand extends Command
{
    protected string $name = 'grid:put';
    protected string $signature = 'grid:put {map : Distributed map name} {key : Key to write} {value : Value or JSON payload} {--ttl=0 : TTL in seconds} {--cluster= : Cluster name}';
    protected string $description = 'Put or update an entry in an In-Memory Data Grid map with optional TTL';

    public function handle(): int
    {
        /** @var IMDGManager $manager */
        $manager = function_exists('imdg') ? imdg() : (function_exists('app') && app()->has('imdg') ? app('imdg') : new IMDGManager());

        $mapName = (string) $this->argument(0);
        $key = (string) $this->argument(1);
        $rawValue = (string) $this->argument(2);
        $ttl = (int) $this->option('ttl');
        $clusterName = $this->option('cluster') ? (string) $this->option('cluster') : null;

        if ($mapName === '' || $key === '') {
            $this->error("Usage: php netfusion grid:put <map> <key> <value> [--ttl=0]");
            return 1;
        }

        $value = json_decode($rawValue, true);
        if ($value === null && json_last_error() !== JSON_ERROR_NONE) {
            $value = $rawValue;
        }

        $cluster = $manager->cluster($clusterName);
        $map = $cluster->getMap($mapName);

        $startTime = microtime(true);
        $prev = $map->put($key, $value, $ttl);
        $latencyMs = round((microtime(true) - $startTime) * 1000, 3);

        $partitionId = $cluster->getPartitionMap()->getPartitionId($key);

        $this->info("✔ Successfully stored entry into IMDG map [{$mapName}].");
        $this->line("Key              : {$key}");
        $this->line("Partition ID     : {$partitionId}");
        $this->line("TTL (seconds)    : " . ($ttl > 0 ? $ttl : 'Infinite'));
        $this->line("Write Latency    : {$latencyMs} ms");
        $this->line("Previous Value   : " . ($prev !== null ? (is_scalar($prev) ? (string)$prev : json_encode($prev)) : '(none)'));

        return 0;
    }
}
