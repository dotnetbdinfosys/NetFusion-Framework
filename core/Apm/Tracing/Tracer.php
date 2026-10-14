<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Apm\Tracing;

/**
 * Class Tracer
 *
 * Distributed tracing coordinator.
 *
 * Creates spans, maintains parent-child relationships, and exports
 * completed spans to an in-memory store or external backend.
 *
 * W3C TraceContext compliant: propagates traceparent headers.
 */
class Tracer
{
    /** @var Span[] Completed spans */
    private array $completedSpans = [];

    /** @var Span|null Active root span */
    private ?Span $activeSpan = null;

    /** @var SpanContext|null Current trace context (from incoming request) */
    private ?SpanContext $currentContext = null;

    public function __construct(
        private readonly string $serviceName = 'netfusion'
    ) {}

    // ------------------------------------------------------------------
    // Span lifecycle
    // ------------------------------------------------------------------

    /**
     * Start a new span. If a traceparent header is provided, link to that trace.
     *
     * @param  string      $name            Operation name (e.g., "db.query", "http.request")
     * @param  string|null $traceparentHeader Incoming W3C traceparent header value
     * @param  array       $tags            Initial tags
     */
    public function startSpan(
        string  $name,
        ?string $traceparentHeader = null,
        array   $tags = []
    ): Span {
        $parentContext = null;

        if ($traceparentHeader !== null) {
            $parentContext = SpanContext::fromTraceparent($traceparentHeader);
        } elseif ($this->currentContext !== null) {
            $parentContext = $this->currentContext;
        }

        $context = $parentContext !== null
            ? SpanContext::child($parentContext)
            : SpanContext::generate();

        $this->currentContext = $context;

        $span = new Span($name, $context, $parentContext);
        $span->setTags(array_merge(['service' => $this->serviceName], $tags));

        $this->activeSpan = $span;
        return $span;
    }

    /**
     * Finish a span and store it in the completed spans list.
     */
    public function finishSpan(Span $span): void
    {
        $span->finish();
        $this->completedSpans[] = $span;

        if ($this->activeSpan === $span) {
            $this->activeSpan = null;
        }
    }

    /**
     * Run a callable within a span. Auto-finishes (even on exception).
     *
     * @template T
     * @param  string   $name
     * @param  callable(Span): T $callback
     * @return T
     */
    public function trace(string $name, callable $callback, array $tags = []): mixed
    {
        $span = $this->startSpan($name, null, $tags);
        try {
            $result = $callback($span);
            $span->ok();
            return $result;
        } catch (\Throwable $e) {
            $span->error($e->getMessage());
            throw $e;
        } finally {
            $this->finishSpan($span);
        }
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getActiveSpan(): ?Span { return $this->activeSpan; }
    public function getServiceName(): string { return $this->serviceName; }

    /** @return Span[] */
    public function getCompletedSpans(): array { return $this->completedSpans; }

    public function clearSpans(): void { $this->completedSpans = []; }

    public function getCurrentTraceparent(): ?string
    {
        return $this->currentContext?->toTraceparent();
    }

    /**
     * Export all completed spans as an array (for Jaeger / Zipkin / OTLP).
     */
    public function exportSpans(): array
    {
        return array_map(fn(Span $s) => $s->toArray(), $this->completedSpans);
    }
}
