<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Gateway\Models;

/**
 * Represents the gateway response returned to the client.
 * Carries status, headers, and body from the proxied upstream.
 */
class GatewayResponse
{
    /** @var array<string, string[]> */
    private array $headers;

    /**
     * @param int                     $statusCode HTTP status code
     * @param string                  $body       Response body
     * @param array<string, string[]> $headers    Response headers
     * @param float                   $latencyMs  Total gateway latency in milliseconds
     * @param string                  $upstreamId ID of the upstream that served the request
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body = '',
        array $headers = [],
        private readonly float $latencyMs = 0.0,
        private readonly string $upstreamId = ''
    ) {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $key = strtolower($name);
            $normalized[$key] = is_array($value) ? $value : [$value];
        }
        $this->headers = $normalized;
    }

    /**
     * Creates a simple text/plain response.
     */
    public static function text(int $status, string $body): self
    {
        return new self($status, $body, ['content-type' => ['text/plain']]);
    }

    /**
     * Creates a JSON response.
     */
    public static function json(int $status, array $data): self
    {
        return new self(
            statusCode: $status,
            body: json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            headers: ['content-type' => ['application/json']],
        );
    }

    /**
     * Creates a gateway error response.
     */
    public static function error(int $status, string $message, string $detail = ''): self
    {
        return self::json($status, [
            'error'   => $message,
            'detail'  => $detail,
            'status'  => $status,
        ]);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLatencyMs(): float
    {
        return $this->latencyMs;
    }

    public function getUpstreamId(): string
    {
        return $this->upstreamId;
    }

    /**
     * Returns a flat header map (first value per header).
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        $flat = [];
        foreach ($this->headers as $name => $values) {
            $flat[$name] = $values[0] ?? '';
        }
        return $flat;
    }

    public function getHeader(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;
        return $values ? $values[0] : null;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Returns a new response with an additional header.
     */
    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[strtolower($name)] = [$value];
        return new self(
            statusCode: $this->statusCode,
            body: $this->body,
            headers: $headers,
            latencyMs: $this->latencyMs,
            upstreamId: $this->upstreamId,
        );
    }

    /**
     * Sends this response to the HTTP client (outputs headers and body).
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $values) {
                $headerName = ucwords(str_replace('-', ' ', $name), ' ');
                $headerName = str_replace(' ', '-', $headerName);
                foreach ($values as $value) {
                    header($headerName . ': ' . $value, false);
                }
            }
        }
        echo $this->body;
    }

    public function toArray(): array
    {
        return [
            'status'      => $this->statusCode,
            'body'        => $this->body,
            'headers'     => $this->getHeaders(),
            'latency_ms'  => $this->latencyMs,
            'upstream_id' => $this->upstreamId,
        ];
    }
}
