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

class OtlpHttpExporter implements ExporterInterface
{
    public function __construct(
        protected string $endpoint = 'http://localhost:4318/v1/metrics',
        protected array $headers = []
    ) {
    }

    public function getContentType(): string
    {
        return 'application/json';
    }

    /**
     * @param MetricInterface[] $metrics
     * @return string
     */
    public function export(array $metrics): string
    {
        $otlpMetrics = [];

        foreach ($metrics as $metric) {
            $metricData = [
                'name' => $metric->getName(),
                'description' => $metric->getDescription(),
                'unit' => $metric->getUnit(),
            ];

            $type = $metric->getType();
            if ($type === 'counter') {
                $dataPoints = [];
                foreach ($metric->collect() as $sample) {
                    $attrs = [];
                    foreach ($sample->labels as $k => $v) {
                        $attrs[] = ['key' => $k, 'value' => ['stringValue' => (string)$v]];
                    }
                    $dataPoints[] = [
                        'timeUnixNano' => (string)((int)($sample->timestamp * 1e9)),
                        'asDouble' => (float)$sample->value,
                        'attributes' => $attrs,
                    ];
                }
                $metricData['sum'] = [
                    'dataPoints' => $dataPoints,
                    'isMonotonic' => true,
                    'aggregationTemporality' => 2, // CUMULATIVE
                ];
            } elseif ($type === 'gauge') {
                $dataPoints = [];
                foreach ($metric->collect() as $sample) {
                    $attrs = [];
                    foreach ($sample->labels as $k => $v) {
                        $attrs[] = ['key' => $k, 'value' => ['stringValue' => (string)$v]];
                    }
                    $dataPoints[] = [
                        'timeUnixNano' => (string)((int)($sample->timestamp * 1e9)),
                        'asDouble' => (float)$sample->value,
                        'attributes' => $attrs,
                    ];
                }
                $metricData['gauge'] = [
                    'dataPoints' => $dataPoints,
                ];
            }

            $otlpMetrics[] = $metricData;
        }

        $payload = [
            'resourceMetrics' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'NetFusion']],
                            ['key' => 'telemetry.sdk.language', 'value' => ['stringValue' => 'php']],
                        ],
                    ],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'netfusion.telemetry', 'version' => '1.0.0'],
                            'metrics' => $otlpMetrics,
                        ],
                    ],
                ],
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
