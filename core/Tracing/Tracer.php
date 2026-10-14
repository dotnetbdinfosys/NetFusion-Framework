<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tracing/Tracer.php
| Module    : Tracing
| Namespace : NetFusion\Core\Tracing
| Purpose   : Central tracer manager orchestrating trace lifecycle and span hierarchy.
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

namespace NetFusion\Core\Tracing;

use Throwable;

/**
 * Class Tracer
 *
 * Central tracing service implementing OpenTelemetry-aligned distributed tracing.
 *
 * @package NetFusion\Core\Tracing
 */
class Tracer
{
    protected ?TraceContext $rootContext = null;

    /**
     * Active spans stack (LIFO).
     *
     * @var array<Span>
     */
    protected array $activeStack = [];

    /**
     * All completed and collected spans in this request/process.
     *
     * @var array<Span>
     */
    protected array $spans = [];

    /**
     * Start or adopt a distributed trace context.
     *
     * @param string|null $traceId
     * @param string|null $traceparent W3C traceparent header string
     * @return TraceContext
     */
    public function startTrace(?string $traceId = null, ?string $traceparent = null): TraceContext
    {
        if ($traceparent !== null) {
            $parsed = TraceContext::fromTraceparent($traceparent);
            if ($parsed !== null) {
                $this->rootContext = $parsed;
                return $this->rootContext;
            }
        }

        $this->rootContext = TraceContext::create($traceId);
        return $this->rootContext;
    }

    /**
     * Get the current active context (from active span or root).
     */
    public function currentContext(): ?TraceContext
    {
        $active = $this->getActiveSpan();
        if ($active !== null) {
            return $active->getContext();
        }

        return $this->rootContext;
    }

    /**
     * Start a new span in the current trace hierarchy.
     *
     * @param string $name
     * @param array  $attributes
     * @return Span
     */
    public function startSpan(string $name, array $attributes = []): Span
    {
        $activeSpan = $this->getActiveSpan();

        if ($activeSpan !== null) {
            // Nested child span
            $context = $activeSpan->getContext()->childSpan();
        } elseif ($this->rootContext !== null) {
            // First span under an existing root context
            $context = new TraceContext(
                traceId     : $this->rootContext->traceId,
                spanId      : $this->rootContext->spanId,
                parentSpanId: $this->rootContext->parentSpanId,
                traceFlags  : $this->rootContext->traceFlags,
                tracestate  : $this->rootContext->tracestate
            );
        } else {
            // Entirely new trace and root span
            $context = $this->startTrace();
        }

        $span = new Span($name, $context, $attributes);
        $this->activeStack[] = $span;

        return $span;
    }

    /**
     * End a span and remove it from active stack.
     *
     * @param Span|null $span
     * @return Span|null
     */
    public function endSpan(?Span $span = null): ?Span
    {
        if ($span === null) {
            $span = array_pop($this->activeStack);
        } else {
            // Remove specific span from active stack
            foreach ($this->activeStack as $i => $s) {
                if ($s === $span) {
                    array_splice($this->activeStack, $i, 1);
                    break;
                }
            }
        }

        if ($span !== null) {
            if (! $span->hasEnded()) {
                $span->end();
            }
            $this->spans[] = $span;
        }

        return $span;
    }

    /**
     * Get the currently active span at the top of the stack.
     */
    public function getActiveSpan(): ?Span
    {
        $count = count($this->activeStack);
        return $count > 0 ? $this->activeStack[$count - 1] : null;
    }

    /**
     * Measure the execution time of a callable within a span.
     *
     * @template T
     * @param string   $name
     * @param callable(): T $callback
     * @param array    $attributes
     * @return T
     * @throws Throwable
     */
    public function measure(string $name, callable $callback, array $attributes = []): mixed
    {
        $span = $this->startSpan($name, $attributes);

        try {
            $result = $callback();
            $this->endSpan($span);
            return $result;
        } catch (Throwable $e) {
            $span->recordException($e);
            $this->endSpan($span);
            throw $e;
        }
    }

    /**
     * Get all collected spans.
     *
     * @return array<Span>
     */
    public function getSpans(): array
    {
        return $this->spans;
    }

    /**
     * Export all collected spans to raw array format.
     *
     * @return array<array<string, mixed>>
     */
    public function export(): array
    {
        return array_map(fn(Span $s) => $s->toArray(), $this->spans);
    }

    /**
     * Reset and flush all spans and trace state.
     */
    public function flush(): void
    {
        $this->activeStack = [];
        $this->spans = [];
        $this->rootContext = null;
    }
}
