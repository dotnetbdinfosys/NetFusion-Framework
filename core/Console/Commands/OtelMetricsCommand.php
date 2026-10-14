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
 * Class OtelMetricsCommand
 *
 * CLI: php netfusion otel:metrics [--format=text|json|prometheus]
 */
class OtelMetricsCommand extends Command
{
    protected string $name        = 'otel:metrics';
    protected string $description = 'Display aggregated real-time metric instruments snapshot';

    public function handle(array $args = []): int
    {
        $format = is_string($args['format'] ?? null) ? $args['format'] : (string) $this->option('format', 'text');
        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--format=')) {
                $format = substr($arg, 9);
            }
        }

        try {
            $otel = ObservabilityManager::getInstance();

            if (strtolower($format) === 'prometheus') {
                echo $otel->exportPrometheus() . "\n";
                return 0;
            }

            $metrics = $otel->collectMetrics();

            if (strtolower($format) === 'json') {
                echo json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                return 0;
            }

            echo "\n=== NetFusion OpenTelemetry Metric Instruments Snapshot ===\n";
            echo "============================================================\n";

            if (empty($metrics)) {
                echo "No metric instruments recorded yet.\n\n";
                return 0;
            }

            foreach ($metrics as $metric) {
                $name = $metric['name'];
                $type = strtoupper($metric['type']);
                $unit = $metric['unit'];
                $desc = $metric['description'];
                $count = count($metric['points']);

                echo sprintf("%-30s | %-12s | Unit: %-8s | Points: %d\n", $name, $type, $unit, $count);
                if ($desc !== '') {
                    echo "  Description: {$desc}\n";
                }
                foreach ($metric['points'] as $p) {
                    $attrStr = json_encode($p['attributes']);
                    if ($metric['type'] === 'histogram') {
                        echo "  - Count: {$p['count']}, Sum: {$p['sum']}, P50: {$p['p50']}, P99: {$p['p99']} | Attrs: {$attrStr}\n";
                    } else {
                        echo "  - Value: {$p['value']} | Attrs: {$attrStr}\n";
                    }
                }
                echo str_repeat('-', 60) . "\n";
            }
            echo "\n";

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to display metrics: " . $e->getMessage());
            return 1;
        }
    }
}
