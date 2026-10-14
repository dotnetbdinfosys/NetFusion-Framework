<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tracing/TraceContext.php
| Module    : Tracing
| Namespace : NetFusion\Core\Tracing
| Purpose   : W3C TraceContext implementation for distributed tracing.
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

use InvalidArgumentException;

/**
 * Class TraceContext
 *
 * Implements the W3C Trace Context recommendation (traceparent / tracestate).
 *
 * @package NetFusion\Core\Tracing
 */
class TraceContext
{
    public const VERSION = '00';
    public const FLAG_SAMPLED = '01';
    public const FLAG_NOT_SAMPLED = '00';

    /**
     * @param string      $traceId      32-hex character trace identifier.
     * @param string      $spanId       16-hex character span identifier.
     * @param string|null $parentSpanId 16-hex character parent span identifier if any.
     * @param string      $traceFlags   2-hex character flags (default '01' sampled).
     * @param string|null $tracestate   Optional vendor-specific state string.
     */
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId = null,
        public readonly string $traceFlags = self::FLAG_SAMPLED,
        public readonly ?string $tracestate = null
    ) {
    }

    /**
     * Generate a new root TraceContext.
     *
     * @param string|null $traceId
     * @param bool        $sampled
     * @return static
     */
    public static function create(?string $traceId = null, bool $sampled = true): static
    {
        $traceId = $traceId !== null && preg_match('/^[a-f0-9]{32}$/i', $traceId)
            ? strtolower($traceId)
            : bin2hex(random_bytes(16));

        $spanId = bin2hex(random_bytes(8));
        $flags = $sampled ? self::FLAG_SAMPLED : self::FLAG_NOT_SAMPLED;

        return new static(
            traceId   : $traceId,
            spanId    : $spanId,
            traceFlags: $flags
        );
    }

    /**
     * Parse incoming W3C `traceparent` header string.
     *
     * Format: `version-trace_id-parent_id-trace_flags`
     * Example: `00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01`
     *
     * @param string      $header
     * @param string|null $tracestate
     * @return static|null Returns null if header is malformed.
     */
    public static function fromTraceparent(string $header, ?string $tracestate = null): ?static
    {
        $header = trim($header);
        $parts = explode('-', $header);

        if (count($parts) !== 4) {
            return null;
        }

        [$version, $traceId, $parentSpanId, $flags] = $parts;

        if (strlen($version) !== 2 || strlen($traceId) !== 32 || strlen($parentSpanId) !== 16 || strlen($flags) !== 2) {
            return null;
        }

        if (! ctype_xdigit($version) || ! ctype_xdigit($traceId) || ! ctype_xdigit($parentSpanId) || ! ctype_xdigit($flags)) {
            return null;
        }

        // TraceId and ParentSpanId cannot be all zeroes
        if ($traceId === str_repeat('0', 32) || $parentSpanId === str_repeat('0', 16)) {
            return null;
        }

        // Generate a new span ID for our local execution layer
        $currentSpanId = bin2hex(random_bytes(8));

        return new static(
            traceId     : strtolower($traceId),
            spanId      : $currentSpanId,
            parentSpanId: strtolower($parentSpanId),
            traceFlags  : $flags,
            tracestate  : $tracestate
        );
    }

    public static function generate(?string $traceId = null, bool $sampled = true): static
    {
        return static::create($traceId, $sampled);
    }

    public static function fromHeader(string $header, ?string $tracestate = null): ?static
    {
        return static::fromTraceparent($header, $tracestate);
    }

    /**
     * Format as W3C `traceparent` header string.
     *
     * @return string
     */
    public function toTraceparent(): string
    {
        return sprintf(
            '%s-%s-%s-%s',
            self::VERSION,
            $this->traceId,
            $this->spanId,
            $this->traceFlags
        );
    }

    public function toHeader(): string
    {
        return $this->toTraceparent();
    }

    public function __toString(): string
    {
        return $this->toTraceparent();
    }

    /**
     * Create a child span context under this trace and span.
     *
     * @return static
     */
    public function childSpan(): static
    {
        return new static(
            traceId     : $this->traceId,
            spanId      : bin2hex(random_bytes(8)),
            parentSpanId: $this->spanId,
            traceFlags  : $this->traceFlags,
            tracestate  : $this->tracestate
        );
    }

    /**
     * Check if tracing is sampled.
     */
    public function isSampled(): bool
    {
        return ($this->traceFlags & 1) === 1;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function getTraceFlags(): string
    {
        return $this->traceFlags;
    }

    public function getTracestate(): ?string
    {
        return $this->tracestate;
    }
}
