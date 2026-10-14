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

class TimeSeriesAnomalyCommand extends Command
{
    protected string $signature = 'tsdb:anomaly {metric : Metric name to analyze} {--method=zscore : Detection method (zscore, iqr, ewma, ensemble)} {--threshold= : Optional sensitivity threshold}';
    protected string $description = 'Detect statistical anomalies, outliers, and traffic spikes in time-series metrics';

    public function handle(): int
    {
        /** @var TimeSeriesManager $tsdb */
        $tsdb = function_exists('app') && app()->has('tsdb') ? app('tsdb') : new TimeSeriesManager();

        $metric = (string)$this->argument('metric');
        $method = (string)($this->option('method') ?: 'zscore');
        $threshold = $this->option('threshold') ? (float)$this->option('threshold') : null;

        $series = $tsdb->query($metric, microtime(true) - 86400, microtime(true));

        if ($series->count() < 3) {
            $this->error("Insufficient data points ({$series->count()}) for anomaly detection. At least 3 points required.");
            return 1;
        }

        $opts = $threshold ? ['threshold' => $threshold, 'multiplier' => $threshold] : [];
        $result = match ($method) {
            'iqr' => $tsdb->detector()->iqr($series, $opts),
            'ewma' => $tsdb->detector()->ewma($series, $opts),
            'ensemble' => $tsdb->detector()->ensemble($series, $opts),
            default => $tsdb->detector()->zscore($series, $opts),
        };

        $this->info("=== Anomaly Detection: {$metric} (Method: {$method}) ===");
        $this->line("Is Anomaly    : " . ($result->isAnomaly ? '<error>YES</error>' : '<info>NO</info>'));
        $this->line("Anomaly Score : " . $result->score);
        $this->line("Actual Value  : " . $result->actualValue);
        $this->line("Expected Baseline: " . $result->expectedValue);
        $this->line("Threshold     : " . $result->threshold);

        if (!empty($result->anomalies)) {
            $this->info("\n--- Flagged Anomalies (" . count($result->anomalies) . ") ---");
            foreach ($result->anomalies as $a) {
                $this->line(sprintf(' • [%s] Value: %.2f (Score: %.2f) - %s', date('H:i:s', (int)$a['timestamp']), $a['value'], $a['score'], $a['message']));
            }
        }

        return 0;
    }
}
