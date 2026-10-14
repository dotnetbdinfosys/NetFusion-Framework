<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Exporters;

use NetFusion\Core\Observability\Tracing\Span;

/**
 * Class OtlpHttpJsonExporter
 *
 * Exports traces and metrics conforming to OpenTelemetry Protocol (OTLP/HTTP JSON 1.x).
 */
class OtlpHttpJsonExporter implements ExporterInterface
{
    protected string $serviceName;
    protected string $serviceVersion;

    public function __construct(string $serviceName = 'netfusion-app', string $serviceVersion = '1.0.0')
    {
        $this->serviceName = $serviceName;
        $this->serviceVersion = $serviceVersion;
    }

    /**
     * Map PHP value to OTLP AnyValue structure.
     */
    protected function mapAnyValue(mixed $val): array
    {
        if (is_int($val)) {
            return ['intValue' => (string) $val];
        }
        if (is_float($val)) {
            return ['doubleValue' => $val];
        }
        if (is_bool($val)) {
            return ['boolValue' => $val];
        }
        if (is_array($val)) {
            $values = [];
            foreach ($val as $item) {
                $values[] = $this->mapAnyValue($item);
            }
            return ['arrayValue' => ['values' => $values]];
        }
        return ['stringValue' => (string) $val];
    }

    /**
     * Map dictionary of attributes to OTLP KeyValue array.
     */
    protected function mapAttributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $k => $v) {
            $result[] = [
                'key' => (string) $k,
                'value' => $this->mapAnyValue($v),
            ];
        }
        return $result;
    }

    /**
     * Export Spans into OTLP/HTTP JSON structure.
     */
    public function exportTraces(array $spans): array
    {
        $otlpSpans = [];

        foreach ($spans as $span) {
            if (!$span instanceof Span) {
                continue;
            }

            $startNano = (string) (int) ($span->getStartTime() * 1e9);
            $endNano = (string) (int) (($span->getEndTime() ?? microtime(true)) * 1e9);

            $events = [];
            foreach ($span->getEvents() as $ev) {
                $events[] = [
                    'timeUnixNano' => (string) (int) (($ev['timestamp'] ?? microtime(true)) * 1e9),
                    'name' => $ev['name'],
                    'attributes' => $this->mapAttributes($ev['attributes'] ?? []),
                ];
            }

            $otlpSpan = [
                'traceId' => $span->getTraceId(),
                'spanId' => $span->getSpanId(),
                'parentSpanId' => $span->getParentSpanId() ?? '',
                'name' => $span->getName(),
                'kind' => $this->mapSpanKind($span->getKind()),
                'startTimeUnixNano' => $startNano,
                'endTimeUnixNano' => $endNano,
                'attributes' => $this->mapAttributes($span->getAttributes()),
                'events' => $events,
                'status' => [
                    'code' => $span->getStatusCode(),
                    'message' => $span->getStatusMessage(),
                ],
            ];

            $otlpSpans[] = $otlpSpan;
        }

        return [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => $this->mapAttributes([
                            'service.name' => $this->serviceName,
                            'service.version' => $this->serviceVersion,
                            'telemetry.sdk.language' => 'php',
                            'telemetry.sdk.name' => 'netfusion-otel',
                        ]),
                    ],
                    'scopeSpans' => [
                        [
                            'scope' => [
                                'name' => 'netfusion-tracer',
                                'version' => '1.0.0',
                            ],
                            'spans' => $otlpSpans,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Export Metrics snapshot into OTLP/HTTP JSON structure.
     */
    public function exportMetrics(array $metrics): array
    {
        $otlpMetrics = [];

        foreach ($metrics as $metric) {
            $name = $metric['name'] ?? '';
            $desc = $metric['description'] ?? '';
            $unit = $metric['unit'] ?? '1';
            $type = $metric['type'] ?? 'gauge';
            $points = $metric['points'] ?? [];

            $dataPoints = [];
            foreach ($points as $p) {
                $timeNano = (string) (int) (($p['last_updated'] ?? microtime(true)) * 1e9);
                $dp = [
                    'timeUnixNano' => $timeNano,
                    'attributes' => $this->mapAttributes($p['attributes'] ?? []),
                ];

                if ($type === 'histogram') {
                    $dp['count'] = (string) ($p['count'] ?? 0);
                    $dp['sum'] = (float) ($p['sum'] ?? 0.0);
                    $dp['bucketCounts'] = array_map('strval', $p['bucket_counts'] ?? []);
                    $dp['explicitBounds'] = array_map('floatval', $p['boundaries'] ?? []);
                    $dp['min'] = (float) ($p['min'] ?? 0.0);
                    $dp['max'] = (float) ($p['max'] ?? 0.0);
                } else {
                    $val = $p['value'] ?? 0;
                    if (is_int($val)) {
                        $dp['asInt'] = (string) $val;
                    } else {
                        $dp['asDouble'] = (float) $val;
                    }
                }

                $dataPoints[] = $dp;
            }

            $metricObj = [
                'name' => $name,
                'description' => $desc,
                'unit' => $unit,
            ];

            if ($type === 'counter') {
                $metricObj['sum'] = [
                    'dataPoints' => $dataPoints,
                    'aggregationTemporality' => 2, // CUMULATIVE
                    'isMonotonic' => true,
                ];
            } elseif ($type === 'updowncounter') {
                $metricObj['sum'] = [
                    'dataPoints' => $dataPoints,
                    'aggregationTemporality' => 2,
                    'isMonotonic' => false,
                ];
            } elseif ($type === 'histogram') {
                $metricObj['histogram'] = [
                    'dataPoints' => $dataPoints,
                    'aggregationTemporality' => 2,
                ];
            } else {
                $metricObj['gauge'] = [
                    'dataPoints' => $dataPoints,
                ];
            }

            $otlpMetrics[] = $metricObj;
        }

        return [
            'resourceMetrics' => [
                [
                    'resource' => [
                        'attributes' => $this->mapAttributes([
                            'service.name' => $this->serviceName,
                            'service.version' => $this->serviceVersion,
                        ]),
                    ],
                    'scopeMetrics' => [
                        [
                            'scope' => [
                                'name' => 'netfusion-meter',
                                'version' => '1.0.0',
                            ],
                            'metrics' => $otlpMetrics,
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function mapSpanKind(string $kind): int
    {
        return match ($kind) {
            'INTERNAL' => 1,
            'SERVER'   => 2,
            'CLIENT'   => 3,
            'PRODUCER' => 4,
            'CONSUMER' => 5,
            default    => 0,
        };
    }
}
