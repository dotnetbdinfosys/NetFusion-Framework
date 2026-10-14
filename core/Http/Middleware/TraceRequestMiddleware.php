<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/TraceRequestMiddleware.php
| Module    : Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Distributed tracing middleware injecting W3C TraceContext headers & APM metrics.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Http\Middleware;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Tracing\ApmManager;
use NetFusion\Core\Tracing\Span;
use NetFusion\Core\Tracing\Tracer;
use Throwable;

/**
 * Class TraceRequestMiddleware
 *
 * Intercepts HTTP requests to extract/propagate W3C TraceContext headers,
 * measure latency, trace span hierarchy, and collect APM telemetry.
 *
 * @package NetFusion\Core\Http\Middleware
 */
class TraceRequestMiddleware implements MiddlewareInterface
{
    protected Tracer $tracer;
    protected ApmManager $apm;

    public function __construct(?Tracer $tracer = null, ?ApmManager $apm = null)
    {
        if ($tracer !== null) {
            $this->tracer = $tracer;
        } elseif (function_exists('app') && app()->has('tracer')) {
            $this->tracer = app('tracer');
        } else {
            $this->tracer = new Tracer();
        }

        if ($apm !== null) {
            $this->apm = $apm;
        } elseif (function_exists('app') && app()->has('apm')) {
            $this->apm = app('apm');
        } else {
            $this->apm = new ApmManager();
        }
    }

    /**
     * Handle the incoming request.
     *
     * @param Request  $request
     * @param callable $next
     * @return Response
     * @throws Throwable
     */
    public function handle(Request $request, callable $next): Response
    {
        $traceparent = $request->header('traceparent') ?? $request->header('Traceparent');
        $context = $this->tracer->startTrace(traceparent: $traceparent);

        $spanName = sprintf('HTTP %s %s', $request->method(), '/' . ltrim($request->path(), '/'));
        $span = $this->tracer->startSpan($spanName, [
            'http.method'   => $request->method(),
            'http.target'   => $request->path(),
            'client.ip'     => $request->ip() ?: '127.0.0.1',
        ]);

        $request->setAttribute('trace_id', $context->traceId);
        $request->setAttribute('span_id', $context->spanId);
        $request->setAttribute('traceparent', $context->toTraceparent());
        $request->setAttribute('trace_context', $context);

        $startMemory = memory_get_usage(true);

        try {
            $response = $next($request);

            $statusCode = $response->getStatusCode();
            $span->setAttribute('http.status_code', $statusCode);

            if ($statusCode >= 400) {
                $span->setStatus(Span::STATUS_ERROR, 'HTTP ' . $statusCode);
            }

            $durationMs = $span->durationMs();
            $memoryUsed = max(0, memory_get_peak_usage(true) - $startMemory);

            $this->apm->recordRequest(
                method     : $request->method(),
                uri        : $request->path(),
                statusCode : $statusCode,
                durationMs : $durationMs,
                memoryBytes: $memoryUsed
            );

            $this->tracer->endSpan($span);

            return $response
                ->withHeader('traceparent', $context->toTraceparent())
                ->withHeader('X-Trace-Id', $context->traceId)
                ->withHeader('X-Response-Time', $durationMs . 'ms');
        } catch (Throwable $e) {
            $span->recordException($e);
            $this->tracer->endSpan($span);

            $this->apm->recordRequest(
                method     : $request->method(),
                uri        : $request->path(),
                statusCode : 500,
                durationMs : $span->durationMs(),
                memoryBytes: max(0, memory_get_peak_usage(true) - $startMemory)
            );

            throw $e;
        }
    }
}
