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

namespace NetFusion\Core\Telemetry\Http;

use NetFusion\Core\Http\Middleware\MiddlewareInterface;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Telemetry\Context\TraceContextPropagator;
use NetFusion\Core\Telemetry\TelemetryManager;
use NetFusion\Core\Tracing\TraceContext;

class TraceContextMiddleware implements MiddlewareInterface
{
    protected TelemetryManager $telemetry;

    public function __construct(
        ?TelemetryManager $telemetry = null
    ) {
        $this->telemetry = $telemetry ?? (function_exists('app') && app()->has('telemetry') ? app('telemetry') : new TelemetryManager());
    }

    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);

        // 1. Extract or generate W3C TraceContext & Baggage
        $headers = $request->headers();
        $extracted = TraceContextPropagator::extract($headers);

        $traceContext = $extracted['traceContext'] ?? TraceContext::generate();
        $baggage = $extracted['baggage'];

        $this->telemetry->setTraceContext($traceContext);
        $this->telemetry->setBaggage($baggage);

        // Attach trace context to request attributes
        $request->setAttribute('trace_context', $traceContext);
        $request->setAttribute('trace_id', $traceContext->traceId);
        $request->setAttribute('span_id', $traceContext->spanId);
        $request->setAttribute('baggage', $baggage);

        try {
            /** @var Response $response */
            $response = $next($request);
        } finally {
            $duration = microtime(true) - $start;
            $status = isset($response) && $response instanceof Response ? $response->getStatusCode() : 500;

            // 2. Record HTTP Request Counter & Latency Histogram
            $this->telemetry->recordHttpRequest(
                method: $request->method(),
                path: $request->path(),
                status: $status,
                durationSeconds: $duration
            );
        }

        // 3. Inject W3C Traceparent & Baggage into Response
        $response->setHeader('traceparent', $traceContext->toHeader());
        if ($traceContext->tracestate !== null) {
            $response->setHeader('tracestate', $traceContext->tracestate);
        }
        if (count($baggage) > 0) {
            $response->setHeader('baggage', $baggage->toHeader());
        }

        return $response;
    }
}
