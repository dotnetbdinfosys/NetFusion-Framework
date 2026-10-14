<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Chaos\ChaosManager;

/**
 * Class ChaosReportCommand
 *
 * CLI: php netfusion chaos:report [--format=text|json]
 */
class ChaosReportCommand extends Command
{
    protected string $name        = 'chaos:report';
    protected string $description = 'Generate and display system resiliency telemetry and GameDay audit reports';

    public function handle(array $args = []): int
    {
        $format = is_string($args['format'] ?? null) ? $args['format'] : (string) $this->option('format', 'text');
        if (in_array('--format=json', $args, true)) {
            $format = 'json';
        }

        try {
            $chaos = ChaosManager::getInstance();
            $report = $chaos->generateReport();

            if ($format === 'json') {
                echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                return 0;
            }

            echo "\n=== NetFusion System Resiliency & Chaos Audit Report ===\n";
            echo "========================================================\n";
            echo "Kill Switch Status : " . ($report['kill_switch_active'] ? 'ACTIVE (HALTED)' : 'NORMAL') . "\n";
            echo "Experiments Run    : " . $report['total_experiments_run'] . "\n";
            echo "Successful Runs    : " . $report['successful'] . "\n";
            echo "Failed Runs        : " . $report['failed'] . "\n";
            echo "Aborted Runs       : " . $report['aborted'] . "\n";
            echo "Resiliency Score   : " . $report['average_resiliency_score'] . " / 100 (" . $report['resiliency_grade'] . ")\n";
            echo "Active Injectors   : " . implode(', ', $report['registered_injectors']) . "\n";
            echo "Experiments        : " . (empty($report['registered_experiments']) ? 'None registered' : implode(', ', $report['registered_experiments'])) . "\n";
            echo "Audit Generated At : " . $report['timestamp'] . "\n\n";

            return 0;
        } catch (\Throwable $e) {
            $this->error("Chaos report generation failed: " . $e->getMessage());
            return 1;
        }
    }
}
