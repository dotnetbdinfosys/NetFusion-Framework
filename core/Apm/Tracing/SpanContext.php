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
 * Class SpanContext
 *
 * Carries the W3C TraceContext (trace_id + span_id) for distributed tracing.
 * Follows the W3C Trace Context specification (Level 1).
 *
 * Header format: traceparent: 00-{trace_id}-{parent_id}-{flags}
 */
final class SpanContext
{
    public function __construct(
        private readonly string $traceId,     // 32 hex chars (128-bit)
        private readonly string $spanId,      // 16 hex chars (64-bit)
        private readonly int    $flags = 1    // 01 = sampled
    ) {}

    // ------------------------------------------------------------------
    // Factory
    // ------------------------------------------------------------------

    public static function generate(): self
    {
        return new self(
            bin2hex(random_bytes(16)),  // 32 hex chars
            bin2hex(random_bytes(8))    // 16 hex chars
        );
    }

    public static function fromTraceparent(string $header): ?self
    {
        // traceparent: 00-{traceId}-{parentId}-{flags}
        $parts = explode('-', $header);
        if (count($parts) !== 4) return null;
        return new self($parts[1], $parts[2], (int) hexdec($parts[3]));
    }

    public static function child(self $parent): self
    {
        return new self($parent->traceId, bin2hex(random_bytes(8)), $parent->flags);
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getTraceId(): string { return $this->traceId; }
    public function getSpanId(): string  { return $this->spanId; }
    public function getFlags(): int      { return $this->flags; }
    public function isSampled(): bool    { return ($this->flags & 0x01) === 0x01; }

    public function toTraceparent(): string
    {
        return sprintf('00-%s-%s-%02x', $this->traceId, $this->spanId, $this->flags);
    }

    public function toArray(): array
    {
        return [
            'trace_id'    => $this->traceId,
            'span_id'     => $this->spanId,
            'flags'       => $this->flags,
            'sampled'     => $this->isSampled(),
            'traceparent' => $this->toTraceparent(),
        ];
    }
}
