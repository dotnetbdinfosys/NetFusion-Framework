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
 * Class OtelExportCommand
 *
 * CLI: php netfusion otel:export [--format=otlp|prometheus|zipkin] [--output=filepath]
 */
class OtelExportCommand extends Command
{
    protected string $name        = 'otel:export';
    protected string $description = 'Export captured OpenTelemetry traces and metrics to OTLP, Prometheus, or Zipkin format';

    public function handle(array $args = []): int
    {
        $format = is_string($args['format'] ?? null) ? $args['format'] : (string) $this->option('format', 'otlp');
        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--format=')) {
                $format = substr($arg, 9);
            }
        }

        $outputFile = is_string($args['output'] ?? null) ? $args['output'] : $this->option('output');
        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--output=')) {
                $outputFile = substr($arg, 9);
            }
        }

        try {
            $otel = ObservabilityManager::getInstance();

            $content = match (strtolower($format)) {
                'prometheus' => $otel->exportPrometheus(),
                'zipkin'     => json_encode($otel->exportZipkin(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                default      => json_encode($otel->exportOtlp(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            };

            if ($outputFile !== null && $outputFile !== '') {
                file_put_contents($outputFile, $content);
                $this->info("Exported OpenTelemetry telemetry ({$format}) to file: {$outputFile}");
            } else {
                echo $content . "\n";
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to export OpenTelemetry data: " . $e->getMessage());
            return 1;
        }
    }
}
