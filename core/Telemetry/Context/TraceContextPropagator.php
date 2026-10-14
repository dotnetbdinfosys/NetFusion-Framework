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

namespace NetFusion\Core\Telemetry\Context;

use NetFusion\Core\Tracing\TraceContext;

class TraceContextPropagator
{
    public const TRACEPARENT_HEADER = 'traceparent';
    public const TRACESTATE_HEADER  = 'tracestate';
    public const BAGGAGE_HEADER      = 'baggage';

    /**
     * Extract TraceContext and Baggage from carrier (HTTP headers or array).
     *
     * @param array<string, mixed> $carrier
     * @return array{traceContext: ?TraceContext, baggage: Baggage}
     */
    public static function extract(array $carrier): array
    {
        $normalized = [];
        foreach ($carrier as $key => $value) {
            $normalized[strtolower(str_replace(['HTTP_', '_'], ['', '-'], (string)$key))] = is_array($value) ? implode(',', $value) : (string)$value;
        }

        $traceContext = null;
        if (isset($normalized[self::TRACEPARENT_HEADER])) {
            $tracestate = $normalized[self::TRACESTATE_HEADER] ?? null;
            $traceContext = TraceContext::fromHeader($normalized[self::TRACEPARENT_HEADER], $tracestate);
        }

        $baggage = new Baggage();
        if (isset($normalized[self::BAGGAGE_HEADER])) {
            $baggage = Baggage::fromHeader($normalized[self::BAGGAGE_HEADER]);
        }

        return [
            'traceContext' => $traceContext,
            'baggage' => $baggage,
        ];
    }

    /**
     * Inject TraceContext and Baggage into carrier array.
     *
     * @param array<string, mixed> $carrier
     * @param TraceContext|null $traceContext
     * @param Baggage|null $baggage
     * @return array<string, string>
     */
    public static function inject(array &$carrier, ?TraceContext $traceContext = null, ?Baggage $baggage = null): array
    {
        if ($traceContext !== null) {
            $carrier[self::TRACEPARENT_HEADER] = $traceContext->toHeader();
            if ($traceContext->tracestate !== null) {
                $carrier[self::TRACESTATE_HEADER] = $traceContext->tracestate;
            }
        }

        if ($baggage !== null && count($baggage) > 0) {
            $carrier[self::BAGGAGE_HEADER] = $baggage->toHeader();
        }

        return $carrier;
    }
}
