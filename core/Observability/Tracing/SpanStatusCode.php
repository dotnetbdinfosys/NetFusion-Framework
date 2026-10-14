<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Tracing;

/**
 * Class SpanStatusCode
 *
 * OpenTelemetry Canonical Status Codes.
 */
class SpanStatusCode
{
    public const UNSET = 0;
    public const OK    = 1;
    public const ERROR = 2;

    public static function name(int $code): string
    {
        return match ($code) {
            self::OK    => 'OK',
            self::ERROR => 'ERROR',
            default     => 'UNSET',
        };
    }
}
