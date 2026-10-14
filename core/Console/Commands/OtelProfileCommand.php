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
use NetFusion\Core\Observability\ObservabilityManager;

/**
 * Class OtelProfileCommand
 *
 * CLI: php netfusion otel:profile [--format=summary|flamegraph]
 */
class OtelProfileCommand extends Command
{
    protected string $name        = 'otel:profile';
    protected string $description = 'Display continuous runtime profiling statistics or export FlameGraph data';

    public function handle(array $args = []): int
    {
        $format = is_string($args['format'] ?? null) ? $args['format'] : (string) $this->option('format', 'summary');
        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--format=')) {
                $format = substr($arg, 9);
            }
        }

        try {
            $otel = ObservabilityManager::getInstance();

            if (strtolower($format) === 'flamegraph') {
                $data = $otel->getFlameGraphData();
                echo ($data !== '' ? $data : "# No FlameGraph samples recorded\n") . "\n";
                return 0;
            }

            $summary = $otel->getProfilingSummary();

            echo "\n=== NetFusion Observability Continuous Profiler Summary ===\n";
            echo "============================================================\n";
            echo "Total Samples Recorded : " . $summary['total_samples'] . "\n";
            echo "Total Duration         : " . $summary['total_wall_duration_ms'] . " ms\n";
            echo "Net Memory Allocated   : " . number_format($summary['net_memory_delta_bytes']) . " bytes\n";
            echo "Peak Memory Observed   : " . number_format($summary['max_peak_memory_bytes']) . " bytes\n";
            echo "Active Profiling Scope : " . $summary['active_sessions_count'] . "\n\n";

            return 0;
        } catch (\Throwable $e) {
            $this->error("Profiling inspection failed: " . $e->getMessage());
            return 1;
        }
    }
}
