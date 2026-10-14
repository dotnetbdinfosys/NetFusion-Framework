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

use NetFusion\Core\Analytics\Enums\AggregationType;
use NetFusion\Core\Analytics\Enums\Granularity;
use NetFusion\Core\Console\Command;

/**
 * CLI command to execute real-time scatter-gather queries on the OLAP analytical engine.
 * Usage: php netfusion druid:query <datasource> [--groupby=country] [--sum=revenue] [--count] [--limit=10]
 */
class DruidQueryCommand extends Command
{
    protected string $name = 'druid:query';
    protected string $description = 'Execute real-time OLAP aggregation queries across columnar segments';

    public function handle(array $args = []): int
    {
        $dataSource = null;
        $groupBy = [];
        $sumFields = [];
        $hasCount = false;
        $limit = 50;

        $positional = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = (string)$args[$i];
            if (str_starts_with($arg, '--groupby=')) {
                $groupBy = explode(',', substr($arg, 10));
            } elseif (str_starts_with($arg, '--sum=')) {
                $sumFields = explode(',', substr($arg, 6));
            } elseif ($arg === '--count') {
                $hasCount = true;
            } elseif (str_starts_with($arg, '--limit=')) {
                $limit = (int)substr($arg, 8);
            } elseif (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        if (empty($positional) && !empty($this->arguments)) {
            $positional = $this->arguments;
        }

        $dataSource = $positional[0] ?? null;

        if ($dataSource === null) {
            $this->error('Usage: php netfusion druid:query <datasource> [--groupby=dim1,dim2] [--sum=metric1] [--count]');
            return 1;
        }

        $analytics = function_exists('analytics') ? analytics() : $this->app->make(\NetFusion\Core\Analytics\AnalyticsManager::class);
        $query = $analytics->query($dataSource);

        if (!empty($groupBy)) {
            $query->groupBy($groupBy);
        }

        foreach ($sumFields as $sumField) {
            $query->sum(trim($sumField));
        }

        if ($hasCount || empty($sumFields)) {
            $query->count('total_events');
        }

        $query->limit($limit);

        $results = $analytics->execute($query);

        if (empty($results)) {
            $this->line("No analytical records found in datasource '{$dataSource}'.");
            return 0;
        }

        $this->info("OLAP Analytical Query Results ({$dataSource}): " . count($results) . " row(s)");

        // Pretty print tabular output
        $headers = array_keys($results[0]);
        $colWidths = [];
        foreach ($headers as $h) {
            $colWidths[$h] = max(strlen($h), 12);
        }
        foreach ($results as $row) {
            foreach ($headers as $h) {
                $valStr = (string)($row[$h] ?? '');
                $colWidths[$h] = max($colWidths[$h], strlen($valStr));
            }
        }

        $headerLine = '';
        foreach ($headers as $h) {
            $headerLine .= sprintf("%-{$colWidths[$h]}s  ", strtoupper($h));
        }
        $this->line($headerLine);
        $this->line(str_repeat('-', strlen($headerLine)));

        foreach ($results as $row) {
            $rowLine = '';
            foreach ($headers as $h) {
                $rowLine .= sprintf("%-{$colWidths[$h]}s  ", (string)($row[$h] ?? ''));
            }
            $this->line($rowLine);
        }

        return 0;
    }
}
