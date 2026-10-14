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
 * CLI command to inspect columnar segments and metadata in the OLAP engine.
 * Usage: php netfusion druid:segments [datasource]
 */
class DruidSegmentsCommand extends Command
{
    protected string $name = 'druid:segments';
    protected string $description = 'Inspect active historical and real-time columnar segments';

    public function handle(array $args = []): int
    {
        $dataSource = $args[0] ?? ($this->arguments[0] ?? null);

        $analytics = function_exists('analytics') ? analytics() : $this->app->make(\NetFusion\Core\Analytics\AnalyticsManager::class);
        $segManager = $analytics->getSegmentManager();

        $dataSources = $dataSource !== null ? [$dataSource] : $segManager->listDataSources();

        if (empty($dataSources)) {
            $this->line('No columnar segments stored in OLAP catalog.');
            return 0;
        }

        $this->info('OLAP Columnar Segment Storage Catalog:');
        $this->line(sprintf('%-20s %-32s %-12s %-20s %-20s', 'DATASOURCE', 'SEGMENT ID', 'ROW COUNT', 'START TIME', 'END TIME'));
        $this->line(str_repeat('-', 110));

        foreach ($dataSources as $ds) {
            $segments = $segManager->getSegmentsForInterval($ds, 0, 2147483647);
            foreach ($segments as $seg) {
                $this->line(sprintf(
                    '%-20s %-32s %-12d %-20s %-20s',
                    $seg->getDataSource(),
                    $seg->getId(),
                    $seg->getRowCount(),
                    date('Y-m-d H:i:s', $seg->getStartTime()),
                    date('Y-m-d H:i:s', $seg->getEndTime())
                ));
            }
        }

        return 0;
    }
}
