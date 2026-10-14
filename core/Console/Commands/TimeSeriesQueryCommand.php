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

class TimeSeriesQueryCommand extends Command
{
    protected string $signature = 'tsdb:query {metric : Metric name to query} {--from= : Start timestamp} {--to= : End timestamp} {--agg= : Aggregation function} {--step= : Bucket step in seconds}';
    protected string $description = 'Query and display time-series metric points and statistical summary';

    public function handle(): int
    {
        /** @var TimeSeriesManager $tsdb */
        $tsdb = function_exists('app') && app()->has('tsdb') ? app('tsdb') : new TimeSeriesManager();

        $metric = (string)$this->argument('metric');
        $from = (float)($this->option('from') ?: (microtime(true) - 3600));
        $to = (float)($this->option('to') ?: microtime(true));
        $agg = $this->option('agg');
        $step = $this->option('step') ? (int)$this->option('step') : null;

        $series = $tsdb->query($metric, $from, $to, aggregation: $agg, stepSeconds: $step);

        $this->info("=== TSDB Query: {$metric} ===");
        $this->line("Time Range : " . date('Y-m-d H:i:s', (int)$from) . " to " . date('Y-m-d H:i:s', (int)$to));
        $this->line("Data Points: " . $series->count());

        if ($series->count() > 0) {
            $this->line("Average    : " . round($series->aggregate('avg'), 3));
            $this->line("Min / Max  : " . round($series->aggregate('min'), 3) . " / " . round($series->aggregate('max'), 3));
            $this->line("P95 / P99  : " . round($series->aggregate('p95'), 3) . " / " . round($series->aggregate('p99'), 3));

            $this->info("\n--- Recent Points ---");
            $points = array_slice($series->getPoints(), -10);
            foreach ($points as $p) {
                $this->line(sprintf(' • %s : %.3f', date('H:i:s', (int)$p->timestamp), $p->value));
            }
        }

        return 0;
    }
}
