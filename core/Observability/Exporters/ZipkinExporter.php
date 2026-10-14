<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Exporters;

use NetFusion\Core\Observability\Tracing\Span;

/**
 * Class ZipkinExporter
 *
 * Exports traces conforming to the Zipkin v2 JSON specification.
 */
class ZipkinExporter implements ExporterInterface
{
    protected string $serviceName;

    public function __construct(string $serviceName = 'netfusion-service')
    {
        $this->serviceName = $serviceName;
    }

    public function exportTraces(array $spans): array
    {
        $zipkinSpans = [];

        foreach ($spans as $span) {
            if (!$span instanceof Span) {
                continue;
            }

            $timestampMicros = (int) ($span->getStartTime() * 1e6);
            $durationMicros = (int) ($span->getDurationMs() * 1000);

            $annotations = [];
            foreach ($span->getEvents() as $ev) {
                $annotations[] = [
                    'timestamp' => (int) (($ev['timestamp'] ?? microtime(true)) * 1e6),
                    'value' => $ev['name'],
                ];
            }

            $tags = [];
            foreach ($span->getAttributes() as $k => $v) {
                $tags[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
            }

            $entry = [
                'id' => $span->getSpanId(),
                'traceId' => $span->getTraceId(),
                'name' => $span->getName(),
                'timestamp' => $timestampMicros,
                'duration' => $durationMicros,
                'kind' => $span->getKind(),
                'localEndpoint' => [
                    'serviceName' => $this->serviceName,
                ],
                'tags' => $tags,
                'annotations' => $annotations,
            ];

            if ($span->getParentSpanId() !== null) {
                $entry['parentId'] = $span->getParentSpanId();
            }

            $zipkinSpans[] = $entry;
        }

        return $zipkinSpans;
    }

    public function exportMetrics(array $metrics): array
    {
        return []; // Zipkin does not ingest metrics
    }
}
