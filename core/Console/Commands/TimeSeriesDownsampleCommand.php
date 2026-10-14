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
use NetFusion\Core\Data\TimeSeries\TimeSeriesManager;

class TimeSeriesDownsampleCommand extends Command
{
    protected string $signature = 'tsdb:downsample {--metric= : Metric name to downsample} {--window=300 : Window size in seconds} {--prune : Also prune expired raw points}';
    protected string $description = 'Downsample high-resolution metric points into rollup tables and optionally prune expired raw data';

    public function handle(): int
    {
        /** @var TimeSeriesManager $tsdb */
        $tsdb = function_exists('app') && app()->has('tsdb') ? app('tsdb') : new TimeSeriesManager();

        $metric = $this->option('metric');
        $window = (int)$this->option('window');
        $metrics = $metric ? [(string)$metric] : $tsdb->getStore()->getMetrics();

        $this->info("=== TSDB Downsampling Rollups (Window: {$window}s) ===");

        $totalRollups = 0;
        foreach ($metrics as $m) {
            $count = $tsdb->downsample($m, $window);
            $this->line(" • Downsampled metric [{$m}]: {$count} rollups created.");
            $totalRollups += $count;
        }

        $this->info("Total Rollups Created: {$totalRollups}");

        if ($this->option('prune')) {
            $retentionSec = 604800; // 7 days default
            $olderThan = microtime(true) - $retentionSec;
            $pruned = $tsdb->prune($olderThan);
            $this->line("Pruned {$pruned} expired raw data points.");
        }

        return 0;
    }
}
