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

namespace NetFusion\Core\Telemetry\Exporters;

use NetFusion\Core\Telemetry\Contracts\ExporterInterface;
use NetFusion\Core\Telemetry\Contracts\MetricInterface;

class JsonTelemetryExporter implements ExporterInterface
{
    public function getContentType(): string
    {
        return 'application/json; charset=utf-8';
    }

    /**
     * @param MetricInterface[] $metrics
     * @return string
     */
    public function export(array $metrics): string
    {
        $payload = [
            'service' => 'NetFusion',
            'timestamp' => microtime(true),
            'metrics' => [],
        ];

        foreach ($metrics as $metric) {
            $payload['metrics'][] = [
                'name' => $metric->getName(),
                'type' => $metric->getType(),
                'description' => $metric->getDescription(),
                'unit' => $metric->getUnit(),
                'samples' => array_map(fn($s) => $s->toArray(), $metric->collect()),
            ];
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
