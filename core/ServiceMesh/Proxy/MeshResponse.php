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

namespace NetFusion\Core\ServiceMesh\Proxy;

use JsonSerializable;

class MeshResponse implements JsonSerializable
{
    protected int $statusCode;
    /** @var array<string, string> */
    protected array $headers;
    protected mixed $body;
    protected ?string $instanceId = null;
    protected ?string $subset = null;
    protected float $latencyMs = 0.0;
    protected bool $fromCache = false;
    protected ?string $error = null;

    /**
     * @param int $statusCode
     * @param array<string, string> $headers
     * @param mixed $body
     * @param string|null $instanceId
     * @param string|null $subset
     * @param float $latencyMs
     * @param bool $fromCache
     * @param string|null $error
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        mixed $body = null,
        ?string $instanceId = null,
        ?string $subset = null,
        float $latencyMs = 0.0,
        bool $fromCache = false,
        ?string $error = null
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->instanceId = $instanceId;
        $this->subset = $subset;
        $this->latencyMs = $latencyMs;
        $this->fromCache = $fromCache;
        $this->error = $error;
    }

    public static function ok(mixed $body = null, array $headers = []): self
    {
        return new self(200, $headers, $body);
    }

    public static function error(int $statusCode, string $error, array $headers = []): self
    {
        return new self($statusCode, $headers, ['error' => $error], null, null, 0.0, false, $error);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isError(): bool
    {
        return $this->statusCode >= 400;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[$name] ?? $this->headers[strtolower($name)] ?? $default;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function json(): ?array
    {
        if (is_array($this->body)) {
            return $this->body;
        }

        if (is_string($this->body)) {
            $decoded = json_decode($this->body, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }

    public function getSubset(): ?string
    {
        return $this->subset;
    }

    public function getLatencyMs(): float
    {
        return $this->latencyMs;
    }

    public function isFromCache(): bool
    {
        return $this->fromCache;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function toArray(): array
    {
        return [
            'status_code' => $this->statusCode,
            'is_success' => $this->isSuccess(),
            'body' => $this->body,
            'headers' => $this->headers,
            'instance_id' => $this->instanceId,
            'subset' => $this->subset,
            'latency_ms' => $this->latencyMs,
            'error' => $this->error,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
