<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Edge\Http;

/**
 * Web Standards-Compliant Edge HTTP Request.
 * Encapsulates method, URL, headers, stream/string payload, and edge geo-location attributes.
 */
class EdgeRequest
{
    protected string $method;
    protected string $url;
    /** @var array<string, string> */
    protected array $headers;
    protected string $body;
    /** @var array{country?: string, city?: string, asn?: int, colo?: string, lat?: float, lon?: float} */
    protected array $cf; // Edge geo-location & CDN metadata

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $cf
     */
    public function __construct(
        string $method,
        string $url,
        array $headers = [],
        string $body = '',
        array $cf = []
    ) {
        $this->method = strtoupper($method);
        $this->url = $url;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
        $this->cf = $cf;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPath(): string
    {
        $parsed = parse_url($this->url);
        return $parsed['path'] ?? '/';
    }

    public function getQuery(): string
    {
        $parsed = parse_url($this->url);
        return $parsed['query'] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function getQueryParams(): array
    {
        parse_str($this->getQuery(), $params);
        return $params;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Decode request body as JSON.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        if (trim($this->body) === '') {
            return null;
        }
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCf(): array
    {
        return $this->cf;
    }

    public function getCountry(): string
    {
        return (string)($this->cf['country'] ?? 'XX');
    }

    public function getCity(): string
    {
        return (string)($this->cf['city'] ?? 'Unknown');
    }

    public function getColo(): string
    {
        return (string)($this->cf['colo'] ?? 'DHK');
    }

    /**
     * Clone request with modifications (Request cloning).
     *
     * @param array<string, mixed> $overrides
     */
    public function cloneWith(array $overrides): self
    {
        return new self(
            $overrides['method'] ?? $this->method,
            $overrides['url'] ?? $this->url,
            $overrides['headers'] ?? $this->headers,
            $overrides['body'] ?? $this->body,
            $overrides['cf'] ?? $this->cf
        );
    }
}
