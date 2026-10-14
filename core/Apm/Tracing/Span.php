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
 * Class Span
 *
 * Represents a single unit of work in a distributed trace.
 *
 * A span records:
 *   - operation name
 *   - timing (start/end/duration)
 *   - key-value tags (metadata)
 *   - events (timestamped log points within the span)
 *   - parent SpanContext for causality linking
 */
class Span
{
    private float   $startTime;
    private ?float  $endTime = null;

    /** @var array<string, mixed> */
    private array $tags   = [];

    /** @var array[] timestamped log events */
    private array $events = [];

    /** @var string  span status: ok | error | unset */
    private string $status = 'unset';

    private ?string $errorMessage = null;

    public function __construct(
        private readonly string      $name,
        private readonly SpanContext $context,
        private readonly ?SpanContext $parentContext = null
    ) {
        $this->startTime = microtime(true);
    }

    // ------------------------------------------------------------------
    // Tagging and events
    // ------------------------------------------------------------------

    public function setTag(string $key, mixed $value): self
    {
        $this->tags[$key] = $value;
        return $this;
    }

    public function setTags(array $tags): self
    {
        foreach ($tags as $k => $v) $this->setTag($k, $v);
        return $this;
    }

    public function addEvent(string $name, array $attributes = []): self
    {
        $this->events[] = [
            'name'       => $name,
            'timestamp'  => date('Y-m-d\TH:i:s') . 'Z',
            'attributes' => $attributes,
        ];
        return $this;
    }

    // ------------------------------------------------------------------
    // Status
    // ------------------------------------------------------------------

    public function ok(): self
    {
        $this->status = 'ok';
        return $this;
    }

    public function error(string $message): self
    {
        $this->status       = 'error';
        $this->errorMessage = $message;
        $this->setTag('error', true);
        $this->setTag('error.message', $message);
        return $this;
    }

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------

    public function finish(): self
    {
        if ($this->endTime === null) {
            $this->endTime = microtime(true);
            if ($this->status === 'unset') $this->status = 'ok';
        }
        return $this;
    }

    public function isFinished(): bool { return $this->endTime !== null; }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getName(): string              { return $this->name; }
    public function getContext(): SpanContext      { return $this->context; }
    public function getParentContext(): ?SpanContext { return $this->parentContext; }
    public function getTraceId(): string           { return $this->context->getTraceId(); }
    public function getSpanId(): string            { return $this->context->getSpanId(); }
    public function getTags(): array               { return $this->tags; }
    public function getEvents(): array             { return $this->events; }
    public function getStatus(): string            { return $this->status; }
    public function getStartTime(): float          { return $this->startTime; }
    public function getEndTime(): ?float           { return $this->endTime; }

    /** Duration in milliseconds */
    public function getDurationMs(): float
    {
        $end = $this->endTime ?? microtime(true);
        return round(($end - $this->startTime) * 1000, 3);
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'trace_id'    => $this->getTraceId(),
            'span_id'     => $this->getSpanId(),
            'parent_id'   => $this->parentContext?->getSpanId(),
            'status'      => $this->status,
            'error'       => $this->errorMessage,
            'duration_ms' => $this->getDurationMs(),
            'tags'        => $this->tags,
            'events'      => $this->events,
            'start_time'  => date('Y-m-d\TH:i:s', (int) $this->startTime) . 'Z',
            'end_time'    => $this->endTime ? date('Y-m-d\TH:i:s', (int) $this->endTime) . 'Z' : null,
        ];
    }
}
