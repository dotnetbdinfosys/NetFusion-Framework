<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Tracing;

use Throwable;

/**
 * Class Span
 *
 * OpenTelemetry Span implementation tracking timing, attributes, events, and error status.
 */
class Span
{
    protected string $name;
    protected TraceContext $context;
    protected ?string $parentSpanId;
    protected string $kind;
    protected int $statusCode = SpanStatusCode::UNSET;
    protected string $statusMessage = '';
    protected float $startTime;
    protected ?float $endTime = null;
    protected float $durationMs = 0.0;
    /** @var array<string, mixed> */
    protected array $attributes = [];
    /** @var array<int, array> */
    protected array $events = [];
    /** @var array<int, array> */
    protected array $links = [];
    protected bool $ended = false;

    public function __construct(
        string $name,
        TraceContext $context,
        ?string $parentSpanId = null,
        string $kind = SpanKind::INTERNAL,
        array $attributes = [],
        ?float $startTime = null
    ) {
        $this->name = $name;
        $this->context = $context;
        $this->parentSpanId = $parentSpanId;
        $this->kind = $kind;
        $this->attributes = $attributes;
        $this->startTime = $startTime ?? microtime(true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function updateName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getContext(): TraceContext
    {
        return $this->context;
    }

    public function getTraceId(): string
    {
        return $this->context->getTraceId();
    }

    public function getSpanId(): string
    {
        return $this->context->getSpanId();
    }

    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function setStatus(int $code, string $message = ''): static
    {
        if ($this->ended) {
            return $this;
        }
        $this->statusCode = $code;
        $this->statusMessage = $message;
        return $this;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    public function getDurationMs(): float
    {
        if ($this->durationMs > 0.0) {
            return $this->durationMs;
        }
        $currentEnd = $this->endTime ?? microtime(true);
        return round(($currentEnd - $this->startTime) * 1000, 3);
    }

    public function isEnded(): bool
    {
        return $this->ended;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        if ($this->ended) {
            return $this;
        }
        $this->attributes[$key] = $value;
        return $this;
    }

    public function setAttributes(array $attributes): static
    {
        if ($this->ended) {
            return $this;
        }
        foreach ($attributes as $k => $v) {
            $this->attributes[(string) $k] = $v;
        }
        return $this;
    }

    public function addEvent(string $name, array $attributes = [], ?float $timestamp = null): static
    {
        if ($this->ended) {
            return $this;
        }
        $this->events[] = [
            'name' => $name,
            'attributes' => $attributes,
            'timestamp' => $timestamp ?? microtime(true),
        ];
        return $this;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function addLink(string $traceId, string $spanId, array $attributes = []): static
    {
        $this->links[] = [
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'attributes' => $attributes,
        ];
        return $this;
    }

    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Record an exception according to OpenTelemetry conventions.
     */
    public function recordException(Throwable $exception, array $attributes = []): static
    {
        $this->setStatus(SpanStatusCode::ERROR, $exception->getMessage());

        $this->addEvent('exception', array_merge([
            'exception.type' => get_class($exception),
            'exception.message' => $exception->getMessage(),
            'exception.stacktrace' => $exception->getTraceAsString(),
            'exception.code' => $exception->getCode(),
        ], $attributes));

        return $this;
    }

    /**
     * Mark the span as ended.
     */
    public function end(?float $timestamp = null): static
    {
        if ($this->ended) {
            return $this;
        }
        $this->endTime = $timestamp ?? microtime(true);
        $this->durationMs = round(($this->endTime - $this->startTime) * 1000, 3);
        $this->ended = true;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'trace_id' => $this->getTraceId(),
            'span_id' => $this->getSpanId(),
            'parent_span_id' => $this->parentSpanId,
            'name' => $this->name,
            'kind' => $this->kind,
            'status' => [
                'code' => $this->statusCode,
                'name' => SpanStatusCode::name($this->statusCode),
                'message' => $this->statusMessage,
            ],
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_ms' => $this->getDurationMs(),
            'attributes' => $this->attributes,
            'events' => $this->events,
            'links' => $this->links,
        ];
    }

    public function toJson(int $options = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $options);
    }
}
