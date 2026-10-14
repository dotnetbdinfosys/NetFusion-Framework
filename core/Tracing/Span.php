<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tracing/Span.php
| Module    : Tracing
| Namespace : NetFusion\Core\Tracing
| Purpose   : Trace span representing an isolated operation or latency segment.
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
 * Class Span
 *
 * Represents an OpenTelemetry-compatible span recording execution duration,
 * tags, structured events, and error status.
 *
 * @package NetFusion\Core\Tracing
 */
class Span
{
    public const STATUS_UNSET = 'UNSET';
    public const STATUS_OK = 'OK';
    public const STATUS_ERROR = 'ERROR';

    protected float $startTime;
    protected ?float $endTime = null;
    protected string $status = self::STATUS_OK;
    protected ?string $statusDescription = null;
    protected array $attributes = [];
    protected array $events = [];

    /**
     * @param string       $name    Descriptive operation name (e.g. 'DB query', 'HTTP GET /users')
     * @param TraceContext $context Context containing trace and span identifiers
     * @param array        $attributes Initial span attributes
     * @param float|null   $startTime Optional start timestamp in seconds (defaults to now)
     */
    public function __construct(
        protected string $name,
        protected TraceContext $context,
        array $attributes = [],
        ?float $startTime = null
    ) {
        $this->startTime = $startTime ?? microtime(true);
        $this->attributes = $attributes;
    }

    /**
     * End this span and calculate duration.
     *
     * @param float|null $endTime
     * @return $this
     */
    public function end(?float $endTime = null): self
    {
        $this->endTime = $endTime ?? microtime(true);
        return $this;
    }

    /**
     * Get span duration in milliseconds.
     *
     * @return float
     */
    public function durationMs(): float
    {
        $end = $this->endTime ?? microtime(true);
        return round(max(0.0, ($end - $this->startTime) * 1000.0), 3);
    }

    /**
     * Check if the span has ended.
     */
    public function hasEnded(): bool
    {
        return $this->endTime !== null;
    }

    /**
     * Set a metadata attribute on the span.
     */
    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Bulk set metadata attributes.
     */
    public function setAttributes(array $attributes): self
    {
        foreach ($attributes as $k => $v) {
            $this->attributes[$k] = $v;
        }
        return $this;
    }

    /**
     * Get an attribute value.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get all attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Add a timestamped event to the span.
     *
     * @param string $name
     * @param array  $attributes
     * @return $this
     */
    public function addEvent(string $name, array $attributes = []): self
    {
        $this->events[] = [
            'name'       => $name,
            'timestamp'  => microtime(true),
            'attributes' => $attributes,
        ];
        return $this;
    }

    /**
     * Get all events logged on this span.
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Set span status (OK, ERROR, UNSET).
     */
    public function setStatus(string $status, ?string $description = null): self
    {
        $this->status = $status;
        $this->statusDescription = $description;
        return $this;
    }

    /**
     * Record an exception on the span.
     */
    public function recordException(Throwable $e): self
    {
        $this->setStatus(self::STATUS_ERROR, $e->getMessage());
        $this->addEvent('exception', [
            'exception.type'    => get_class($e),
            'exception.message' => $e->getMessage(),
            'exception.code'    => $e->getCode(),
            'exception.stack'   => $e->getTraceAsString(),
        ]);
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): TraceContext
    {
        return $this->context;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusDescription(): ?string
    {
        return $this->statusDescription;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    /**
     * Export span to structured array.
     */
    public function toArray(): array
    {
        return [
            'name'           => $this->name,
            'trace_id'       => $this->context->traceId,
            'span_id'        => $this->context->spanId,
            'parent_span_id' => $this->context->parentSpanId,
            'start_time'     => $this->startTime,
            'end_time'       => $this->endTime,
            'duration_ms'    => $this->durationMs(),
            'status'         => $this->status,
            'description'    => $this->statusDescription,
            'attributes'     => $this->attributes,
            'events'         => $this->events,
        ];
    }
}
