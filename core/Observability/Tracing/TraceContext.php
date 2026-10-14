<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Tracing;

/**
 * Class TraceContext
 *
 * Implements W3C TraceContext (traceparent & tracestate) and Baggage specification.
 */
class TraceContext
{
    public const TRACEPARENT_HEADER = 'traceparent';
    public const TRACESTATE_HEADER   = 'tracestate';
    public const BAGGAGE_HEADER      = 'baggage';

    protected string $traceId;
    protected string $spanId;
    protected int $traceFlags = 0x01; // 01 = Sampled
    /** @var array<string, string> */
    protected array $traceState = [];
    /** @var array<string, string> */
    protected array $baggage = [];

    public function __construct(
        ?string $traceId = null,
        ?string $spanId = null,
        int $traceFlags = 0x01,
        array $traceState = [],
        array $baggage = []
    ) {
        $this->traceId = $traceId ?? self::generateTraceId();
        $this->spanId = $spanId ?? self::generateSpanId();
        $this->traceFlags = $traceFlags;
        $this->traceState = $traceState;
        $this->baggage = $baggage;
    }

    public static function generateTraceId(): string
    {
        return bin2hex(random_bytes(16)); // 32 hex chars
    }

    public static function generateSpanId(): string
    {
        return bin2hex(random_bytes(8)); // 16 hex chars
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getTraceFlags(): int
    {
        return $this->traceFlags;
    }

    public function isSampled(): bool
    {
        return ($this->traceFlags & 0x01) === 0x01;
    }

    public function setSampled(bool $sampled): static
    {
        if ($sampled) {
            $this->traceFlags |= 0x01;
        } else {
            $this->traceFlags &= ~0x01;
        }
        return $this;
    }

    public function getTraceState(): array
    {
        return $this->traceState;
    }

    public function setTraceStateValue(string $key, string $value): static
    {
        $this->traceState[$key] = $value;
        return $this;
    }

    public function getBaggage(): array
    {
        return $this->baggage;
    }

    public function getBaggageItem(string $key, ?string $default = null): ?string
    {
        return $this->baggage[$key] ?? $default;
    }

    public function setBaggageItem(string $key, string $value): static
    {
        $this->baggage[$key] = $value;
        return $this;
    }

    /**
     * Create a new child context sharing the same traceId and baggage, with a newly generated spanId.
     */
    public function forkChild(): self
    {
        return new self(
            $this->traceId,
            self::generateSpanId(),
            $this->traceFlags,
            $this->traceState,
            $this->baggage
        );
    }

    /**
     * Serialize to W3C traceparent string: `00-{traceId}-{spanId}-{flags}`.
     */
    public function toTraceparent(): string
    {
        $flagsHex = sprintf('%02x', $this->traceFlags);
        return "00-{$this->traceId}-{$this->spanId}-{$flagsHex}";
    }

    /**
     * Parse W3C traceparent string: `00-{traceId}-{spanId}-{flags}`.
     */
    public static function fromTraceparent(string $header): ?self
    {
        $header = trim($header);
        $parts = explode('-', $header);

        if (count($parts) !== 4) {
            return null;
        }

        [$version, $traceId, $spanId, $flagsHex] = $parts;

        // Check lengths: traceId must be 32 hex chars, spanId 16 hex chars
        if (strlen($traceId) !== 32 || strlen($spanId) !== 16 || strlen($flagsHex) !== 2) {
            return null;
        }

        // TraceId and SpanId cannot be all zeros
        if (preg_match('/^0{32}$/', $traceId) || preg_match('/^0{16}$/', $spanId)) {
            return null;
        }

        $flags = hexdec($flagsHex);

        return new self($traceId, $spanId, $flags);
    }

    /**
     * Serialize tracestate array into W3C tracestate header.
     */
    public function toTracestate(): string
    {
        $pairs = [];
        foreach ($this->traceState as $k => $v) {
            $pairs[] = "{$k}={$v}";
        }
        return implode(',', $pairs);
    }

    /**
     * Parse W3C tracestate string: `vendor1=opaqueValue,vendor2=opaqueValue`.
     */
    public static function parseTracestate(string $header): array
    {
        $state = [];
        $parts = explode(',', $header);
        foreach ($parts as $part) {
            $item = trim($part);
            if ($item === '') {
                continue;
            }
            $kv = explode('=', $item, 2);
            if (count($kv) === 2) {
                $state[trim($kv[0])] = trim($kv[1]);
            }
        }
        return $state;
    }

    /**
     * Serialize baggage into W3C baggage header.
     */
    public function toBaggage(): string
    {
        $pairs = [];
        foreach ($this->baggage as $k => $v) {
            $pairs[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        return implode(',', $pairs);
    }

    /**
     * Parse W3C baggage string: `userId=alice,serverNode=DF%2028`.
     */
    public static function parseBaggage(string $header): array
    {
        $baggage = [];
        $parts = explode(',', $header);
        foreach ($parts as $part) {
            $item = trim($part);
            if ($item === '') {
                continue;
            }
            $kv = explode('=', $item, 2);
            if (count($kv) === 2) {
                $baggage[rawurldecode(trim($kv[0]))] = rawurldecode(trim($kv[1]));
            }
        }
        return $baggage;
    }

    /**
     * Extract context from case-insensitive HTTP headers map.
     */
    public static function extractFromHeaders(array $headers): self
    {
        $lower = array_change_key_case($headers, CASE_LOWER);

        $tp = $lower[self::TRACEPARENT_HEADER] ?? null;
        if (is_array($tp)) {
            $tp = $tp[0] ?? null;
        }

        $context = ($tp !== null) ? self::fromTraceparent((string) $tp) : null;
        if ($context === null) {
            $context = new self();
        }

        $ts = $lower[self::TRACESTATE_HEADER] ?? null;
        if (is_array($ts)) {
            $ts = $ts[0] ?? null;
        }
        if ($ts !== null) {
            $context->traceState = self::parseTracestate((string) $ts);
        }

        $bg = $lower[self::BAGGAGE_HEADER] ?? null;
        if (is_array($bg)) {
            $bg = $bg[0] ?? null;
        }
        if ($bg !== null) {
            $context->baggage = self::parseBaggage((string) $bg);
        }

        return $context;
    }

    /**
     * Inject traceparent, tracestate, and baggage into outgoing headers.
     */
    public function injectIntoHeaders(array &$headers): void
    {
        $headers[self::TRACEPARENT_HEADER] = $this->toTraceparent();

        $ts = $this->toTracestate();
        if ($ts !== '') {
            $headers[self::TRACESTATE_HEADER] = $ts;
        }

        $bg = $this->toBaggage();
        if ($bg !== '') {
            $headers[self::BAGGAGE_HEADER] = $bg;
        }
    }
}
