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
 * Web Standards-Compliant Edge HTTP Response.
 * Supports status codes, headers mutation, body streaming/string, JSON, and redirection helpers.
 */
class EdgeResponse
{
    protected int $status;
    protected string $statusText;
    /** @var array<string, string> */
    protected array $headers;
    protected string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $body = '',
        int $status = 200,
        array $headers = [],
        string $statusText = 'OK'
    ) {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
        $this->statusText = $statusText;
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $headers['content-type'] = 'application/json; charset=utf-8';
        return new self(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status, $headers);
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        $headers['content-type'] = 'text/html; charset=utf-8';
        return new self($html, $status, $headers);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['location' => $url]);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getStatusText(): string
    {
        return $this->statusText;
    }

    public function isOk(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $nameLower = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $nameLower) {
                return $v;
            }
        }
        return null;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Parse response body as JSON.
     *
     * @return array<string, mixed>|null
     */
    public function jsonBody(): ?array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
