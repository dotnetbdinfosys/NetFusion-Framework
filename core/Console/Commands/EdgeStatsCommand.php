<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Edge\EdgeWorkerManager;

/**
 * CLI command to inspect Edge Gateway & Isolate Pool telemetry stats.
 * Usage: php netfusion edge:stats
 */
class EdgeStatsCommand extends Command
{
    protected string $name = 'edge:stats';
    protected string $description = 'Display edge runtime, isolate pool, and cache telemetry statistics';

    public function handle(array $args = []): int
    {
        /** @var EdgeWorkerManager $edge */
        $edge = function_exists('app') && app()->has('edge') ? app('edge') : new EdgeWorkerManager();

        $stats = $edge->stats();

        $this->info("=================================================");
        $this->info("  NetFusion Edge Computing Gateway Statistics   ");
        $this->info("=================================================");
        $this->line("Registered Workers: " . $stats['workers_count']);
        $this->line("Active Routes     : " . $stats['routes_count']);
        $this->line("-------------------------------------------------");
        $this->info("V8 Isolate Pool:");
        $this->line("  Total Isolates  : " . $stats['isolates']['total']);
        $this->line("  Idle (Pre-warmed): " . $stats['isolates']['idle']);
        $this->line("  Active (Busy)   : " . $stats['isolates']['active']);
        $this->line("  Max Capacity    : " . $stats['isolates']['maxCapacity']);
        $this->line("-------------------------------------------------");
        $this->info("Edge Response Cache:");
        $this->line("  Cache Entries   : " . $stats['cache']['total_entries']);
        $this->line("  Cache Hits      : " . $stats['cache']['hits']);
        $this->line("  Cache Misses    : " . $stats['cache']['misses']);
        $this->line("  Hit Ratio       : " . ($stats['cache']['hit_ratio'] * 100) . "%");
        $this->info("=================================================");

        return 0;
    }
}
