<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Console/Commands/ApmSummaryCommand.php
| Module    : Console\Commands
| Namespace : NetFusion\Core\Console\Commands
| Purpose   : Command to display APM performance metrics, latencies and slow queries.
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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Tracing\ApmManager;

/**
 * Class ApmSummaryCommand
 *
 * CLI command to view application performance metrics and telemetry summaries.
 *
 * @package NetFusion\Core\Console\Commands
 */
class ApmSummaryCommand extends Command
{
    protected string $name = 'apm:summary';
    protected string $description = 'Display Application Performance Monitoring (APM) telemetry summary';

    public function handle(): int
    {
        /** @var ApmManager $apm */
        $apm = $this->app->has('apm')
            ? $this->app->make('apm')
            : new ApmManager();

        $metrics = $apm->getMetricsSummary();

        $this->line();
        $this->info("================================================================================");
        $this->info("   NetFusion APM — Application Performance Monitoring Telemetry Summary         ");
        $this->info("================================================================================");
        $this->line();

        // 1. HTTP Requests
        $req = $metrics['requests'];
        $this->warn("[1] HTTP Request Latency & Throughput");
        $this->line(sprintf("  %-24s: %d", 'Total Requests', $req['total']));
        $this->line(sprintf("  %-24s: %d (%.2f%%)", 'Error Requests', $req['errors'], $req['error_rate']));
        $this->line(sprintf("  %-24s: %.2f ms", 'Average Latency', $req['latency_avg']));
        $this->line(sprintf("  %-24s: %.2f ms", 'Median (p50) Latency', $req['latency_p50']));
        $this->line(sprintf("  %-24s: %.2f ms", '95th Percentile (p95)', $req['latency_p95']));
        $this->line(sprintf("  %-24s: %.2f ms", '99th Percentile (p99)', $req['latency_p99']));
        $this->line();

        // 2. Database Queries
        $db = $metrics['database'];
        $this->warn("[2] Database Performance & Execution");
        $this->line(sprintf("  %-24s: %d", 'Total Queries Run', $db['total_queries']));
        $this->line(sprintf("  %-24s: %.2f ms", 'Cumulative Query Time', $db['total_time_ms']));
        $this->line(sprintf("  %-24s: %.2f ms", 'Avg Query Execution', $db['avg_time_ms']));
        $this->line(sprintf("  %-24s: %d", 'Slow Queries (> 50ms)', $db['slow_queries']));
        $this->line();

        // 3. Cache & Memory
        $cache = $metrics['cache'];
        $mem = $metrics['memory'];
        $this->warn("[3] Cache & Memory Consumption");
        $this->line(sprintf("  %-24s: %d Hits / %d Misses (%.2f%% Hit Ratio)", 'Cache Telemetry', $cache['hits'], $cache['misses'], $cache['hit_ratio']));
        $this->line(sprintf("  %-24s: %.2f MB", 'Peak Memory Usage', $mem['peak_mb']));
        $this->line(sprintf("  %-24s: %.2f MB", 'Current Memory Usage', $mem['current_mb']));
        $this->line();

        // 4. Slow queries detail if requested or present
        $showSlow = $this->hasOption('slow');
        $threshold = (float) ($this->option('threshold') ?: 50.0);
        $slowList = $apm->getSlowQueries($threshold);

        if ($showSlow || ! empty($slowList)) {
            $this->warn(sprintf("[4] Slow Queries (Threshold >= %.1f ms)", $threshold));
            if (empty($slowList)) {
                $this->success("  No slow queries detected matching the threshold.");
            } else {
                foreach (array_slice($slowList, 0, 10) as $idx => $sq) {
                    $this->line(sprintf("  #%d [%.2f ms] %s", $idx + 1, $sq['duration_ms'], $sq['sql']));
                }
            }
            $this->line();
        }

        $this->success("APM telemetry report generated successfully.");
        return 0;
    }
}
