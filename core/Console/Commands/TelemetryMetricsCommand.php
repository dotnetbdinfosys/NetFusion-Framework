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
use NetFusion\Core\Telemetry\TelemetryManager;
use Throwable;

class TelemetryMetricsCommand extends Command
{
    protected string $name = 'telemetry:metrics';
    protected string $description = 'Display application metrics in Prometheus, JSON or OTLP exposition format';

    public function handle(): int
    {
        /** @var TelemetryManager $telemetry */
        $telemetry = $this->app->make(TelemetryManager::class);

        if ($this->option('reset')) {
            $telemetry->reset();
            $this->success("All telemetry in-memory metrics have been reset.");
            return 0;
        }

        $format = strtolower((string)$this->option('format', 'prometheus'));

        try {
            if ($format === 'json') {
                $output = $telemetry->renderJson();
                $this->line($output);
            } elseif ($format === 'otlp') {
                $output = (string)$telemetry->exporter('otlp')->export($telemetry->getMetrics());
                $this->line($output);
            } else {
                $output = $telemetry->renderPrometheus();
                if (trim($output) === '') {
                    $this->info("No telemetry metrics collected yet.");
                } else {
                    $this->line($output);
                }
            }

            return 0;
        } catch (Throwable $e) {
            $this->error("Failed to render telemetry metrics: " . $e->getMessage());
            return 1;
        }
    }
}
