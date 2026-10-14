<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Tracing;

use Throwable;

/**
 * Class Tracer
 *
 * OpenTelemetry Tracer managing span lifecycle, active scope stack, and context propagation.
 */
class Tracer
{
    protected string $serviceName;
    protected string $serviceVersion;
    protected SpanBuffer $buffer;
    /** @var array<int, Span> */
    protected array $activeSpans = [];
    protected array $resourceAttributes = [];

    public function __construct(
        string $serviceName = 'netfusion-service',
        string $serviceVersion = '1.0.0',
        ?SpanBuffer $buffer = null,
        array $resourceAttributes = []
    ) {
        $this->serviceName = $serviceName;
        $this->serviceVersion = $serviceVersion;
        $this->buffer = $buffer ?? new SpanBuffer();
        $this->resourceAttributes = array_merge([
            'service.name' => $serviceName,
            'service.version' => $serviceVersion,
            'telemetry.sdk.language' => 'php',
            'telemetry.sdk.name' => 'netfusion-otel',
        ], $resourceAttributes);
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getServiceVersion(): string
    {
        return $this->serviceVersion;
    }

    public function getBuffer(): SpanBuffer
    {
        return $this->buffer;
    }

    public function getResourceAttributes(): array
    {
        return $this->resourceAttributes;
    }

    public function getCurrentSpan(): ?Span
    {
        $count = count($this->activeSpans);
        return $count > 0 ? $this->activeSpans[$count - 1] : null;
    }

    /**
     * Start a new span and optionally make it active in scope.
     *
     * @param array $options Options:
     *   - 'context' => TraceContext (optional parent context)
     *   - 'parent_span_id' => string (optional explicit parent ID)
     *   - 'kind' => SpanKind constant
     *   - 'attributes' => array
     *   - 'make_active' => bool (default true)
     */
    public function startSpan(string $name, array $options = []): Span
    {
        $current = $this->getCurrentSpan();
        $parentContext = $options['context'] ?? ($current ? $current->getContext() : null);

        if ($parentContext instanceof TraceContext) {
            $context = $parentContext->forkChild();
            $parentId = $options['parent_span_id'] ?? $parentContext->getSpanId();
        } else {
            $context = new TraceContext();
            $parentId = $options['parent_span_id'] ?? null;
        }

        $kind = $options['kind'] ?? SpanKind::INTERNAL;
        $attributes = array_merge($this->resourceAttributes, $options['attributes'] ?? []);

        $span = new Span($name, $context, $parentId, $kind, $attributes);

        if ($options['make_active'] ?? true) {
            $this->activeSpans[] = $span;
        }

        return $span;
    }

    /**
     * End a span, popping it from active scope and sending to buffer.
     */
    public function endSpan(Span $span, ?float $timestamp = null): void
    {
        $span->end($timestamp);

        // Remove from active spans stack if present
        $key = array_search($span, $this->activeSpans, true);
        if ($key !== false) {
            array_splice($this->activeSpans, $key, 1);
        }

        $this->buffer->push($span);
    }

    /**
     * Execute a callable within a traced span scope, automatically recording errors and ending the span.
     */
    public function span(string $name, callable $callable, array $options = []): mixed
    {
        $span = $this->startSpan($name, $options);

        try {
            $result = $callable($span);
            if ($span->getStatusCode() === SpanStatusCode::UNSET) {
                $span->setStatus(SpanStatusCode::OK);
            }
            return $result;
        } catch (Throwable $e) {
            $span->recordException($e);
            throw $e;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * Extract W3C trace context from incoming HTTP headers.
     */
    public function extractContext(array $headers): TraceContext
    {
        return TraceContext::extractFromHeaders($headers);
    }

    /**
     * Inject W3C trace context from a span into outgoing HTTP headers.
     */
    public function injectContext(Span $span, array &$headers): void
    {
        $span->getContext()->injectIntoHeaders($headers);
    }
}
