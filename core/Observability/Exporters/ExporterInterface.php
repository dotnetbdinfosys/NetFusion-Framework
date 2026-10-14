<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Exporters;

use NetFusion\Core\Observability\Tracing\Span;

/**
 * Interface ExporterInterface
 *
 * Contract for exporting OpenTelemetry traces and metrics to external backends.
 */
interface ExporterInterface
{
    /**
     * Export an array of Spans to target format.
     *
     * @param array<int, Span> $spans
     */
    public function exportTraces(array $spans): mixed;

    /**
     * Export metrics snapshot to target format.
     */
    public function exportMetrics(array $metrics): mixed;
}
