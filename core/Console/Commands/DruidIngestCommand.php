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

/**
 * CLI command to ingest real-time events into the OLAP engine.
 * Usage: php netfusion druid:ingest <datasource> --dims="country=BD,browser=Chrome" --metrics="revenue=150,duration=2.5"
 */
class DruidIngestCommand extends Command
{
    protected string $name = 'druid:ingest';
    protected string $description = 'Ingest real-time streaming events into the OLAP analytical engine';

    public function handle(array $args = []): int
    {
        $dataSource = null;
        $dimsRaw = '';
        $metricsRaw = '';

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string)$args[$i];
            if (str_starts_with($arg, '--dims=')) {
                $dimsRaw = substr($arg, 7);
            } elseif (str_starts_with($arg, '--metrics=')) {
                $metricsRaw = substr($arg, 10);
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $dataSource = $positional[0] ?? null;

        if ($dataSource === null) {
            $this->error('Usage: php netfusion druid:ingest <datasource> [--dims="country=BD,browser=Chrome"] [--metrics="revenue=100"]');
            return 1;
        }

        $dimensions = [];
        if (!empty($dimsRaw)) {
            foreach (explode(',', $dimsRaw) as $pair) {
                if (str_contains($pair, '=')) {
                    [$k, $v] = explode('=', trim($pair), 2);
                    $dimensions[$k] = $v;
                }
            }
        }

        $metrics = [];
        if (!empty($metricsRaw)) {
            foreach (explode(',', $metricsRaw) as $pair) {
                if (str_contains($pair, '=')) {
                    [$k, $v] = explode('=', trim($pair), 2);
                    $metrics[$k] = (float)$v;
                }
            }
        }

        $analytics = function_exists('analytics') ? analytics() : $this->app->make(\NetFusion\Core\Analytics\AnalyticsManager::class);
        $analytics->ingest($dataSource, time(), $dimensions, $metrics);

        $this->info("Successfully ingested event into datasource '{$dataSource}'.");
        return 0;
    }
}
