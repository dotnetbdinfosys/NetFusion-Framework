<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Tracing;

/**
 * Class SpanKind
 *
 * OpenTelemetry SpanKind definitions according to W3C / OTel specifications.
 */
class SpanKind
{
    public const INTERNAL = 'INTERNAL';
    public const SERVER   = 'SERVER';
    public const CLIENT   = 'CLIENT';
    public const PRODUCER = 'PRODUCER';
    public const CONSUMER = 'CONSUMER';
}
