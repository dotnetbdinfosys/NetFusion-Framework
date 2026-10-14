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
 * Represents an inbound request entering the gateway.
 * Wraps HTTP request fields in a value-object.
 */
class GatewayRequest
{
    /** @var array<string, string[]> */
    private array $headers;

    /**
     * @param string                  $method   HTTP method (GET, POST, …)
     * @param string                  $uri      Full URI path (may include query string)
     * @param string                  $host     Host header value
     * @param string                  $body     Request body
     * @param array<string, string[]> $headers  All request headers
     * @param string                  $clientIp Remote client IP address
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly string $host,
        private readonly string $body = '',
        array $headers = [],
        private readonly string $clientIp = '127.0.0.1',
        private readonly array $queryParams = [],
        private readonly array $attributes = []
    ) {
        // Normalize headers to lowercase keys with array values
        $normalized = [];
        foreach ($headers as $name => $value) {
            $key = strtolower($name);
            $normalized[$key] = is_array($value) ? $value : [$value];
        }
        $this->headers = $normalized;
    }

    /**
     * Builds a GatewayRequest from the current PHP superglobals.
     */
    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = [$value];
            }
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $body = file_get_contents('php://input') ?: '';
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        return new self(
            method: $method,
            uri: $uri,
            host: $host,
            body: $body,
            headers: $headers,
            clientIp: $clientIp,
            queryParams: $_GET,
        );
    }

    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Returns only the path portion (without query string).
     */
    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?? '/';
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Returns all headers as a flat [name => value] map.
     * For headers with multiple values, returns the first.
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

    /**
     * Returns the value(s) for a named header, or null if absent.
     *
     * @return string[]|null
     */
    public function getHeaderValues(string $name): ?array
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Returns the first value of a header, or null if absent.
     */
    public function getHeader(string $name): ?string
    {
        $values = $this->getHeaderValues($name);
        return $values ? $values[0] : null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        // attributes is readonly in property but not the cloned array content
        $attrs = $this->attributes;
        $attrs[$key] = $value;
        return new self(
            method: $this->method,
            uri: $this->uri,
            host: $this->host,
            body: $this->body,
            headers: $this->headers,
            clientIp: $this->clientIp,
            queryParams: $this->queryParams,
            attributes: $attrs,
        );
    }

    /**
     * Returns a new request with modified headers.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $merged = $this->headers;
        foreach ($headers as $name => $value) {
            $merged[strtolower($name)] = [$value];
        }
        return new self(
            method: $this->method,
            uri: $this->uri,
            host: $this->host,
            body: $this->body,
            headers: $merged,
            clientIp: $this->clientIp,
            queryParams: $this->queryParams,
            attributes: $this->attributes,
        );
    }

    /**
     * Returns a new request with the given URI.
     */
    public function withUri(string $uri): self
    {
        return new self(
            method: $this->method,
            uri: $uri,
            host: $this->host,
            body: $this->body,
            headers: $this->headers,
            clientIp: $this->clientIp,
            queryParams: $this->queryParams,
            attributes: $this->attributes,
        );
    }

    /**
     * Generates a rate-limit key based on the specified dimension.
     */
    public function getRateLimitKey(string $dimension = 'ip'): string
    {
        return match ($dimension) {
            'ip'      => 'rl:ip:' . $this->clientIp,
            'api_key' => 'rl:key:' . ($this->getHeader('x-api-key') ?? 'anonymous'),
            'user'    => 'rl:user:' . ($this->getAttribute('user_id') ?? 'guest'),
            'tenant'  => 'rl:tenant:' . ($this->getHeader('x-tenant-id') ?? 'default'),
            default   => 'rl:ip:' . $this->clientIp,
        };
    }
}
