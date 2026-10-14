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

use NetFusion\Core\Gateway\Contracts\UpstreamInterface;

/**
 * Represents a single backend upstream server.
 */
class Upstream implements UpstreamInterface
{
    private bool $healthy = true;
    private int $activeConnections = 0;
    private float $averageLatencyMs = 0.0;
    private int $totalRequests = 0;

    public function __construct(
        private readonly string $id,
        private readonly string $scheme,
        private readonly string $host,
        private readonly int $port,
        private readonly int $weight = 1,
        private readonly string $pathPrefix = ''
    ) {}

    /**
     * Static factory: create from a URL string.
     * e.g., "http://backend:8080/api"
     */
    public static function fromUrl(string $url, int $weight = 1): self
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'http';
        $host   = $parts['host'] ?? 'localhost';
        $port   = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path   = $parts['path'] ?? '';

        return new self(
            id: $scheme . '://' . $host . ':' . $port . $path,
            scheme: $scheme,
            host: $host,
            port: $port,
            weight: $weight,
            pathPrefix: $path
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getPathPrefix(): string
    {
        return $this->pathPrefix;
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function markHealthy(): void
    {
        $this->healthy = true;
    }

    public function markUnhealthy(): void
    {
        $this->healthy = false;
    }

    public function getBaseUrl(): string
    {
        $defaultPort = $this->scheme === 'https' ? 443 : 80;
        $portSuffix  = $this->port !== $defaultPort ? ':' . $this->port : '';
        return $this->scheme . '://' . $this->host . $portSuffix . $this->pathPrefix;
    }

    public function incrementConnections(): void
    {
        $this->activeConnections++;
    }

    public function decrementConnections(): void
    {
        if ($this->activeConnections > 0) {
            $this->activeConnections--;
        }
    }

    public function getActiveConnections(): int
    {
        return $this->activeConnections;
    }

    public function recordLatency(float $latencyMs): void
    {
        $this->totalRequests++;
        // Exponential moving average
        if ($this->totalRequests === 1) {
            $this->averageLatencyMs = $latencyMs;
        } else {
            $alpha = 0.2;
            $this->averageLatencyMs = $alpha * $latencyMs + (1 - $alpha) * $this->averageLatencyMs;
        }
    }

    public function getAverageLatencyMs(): float
    {
        return $this->averageLatencyMs;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'scheme'       => $this->scheme,
            'host'         => $this->host,
            'port'         => $this->port,
            'weight'       => $this->weight,
            'path_prefix'  => $this->pathPrefix,
            'healthy'      => $this->healthy,
            'connections'  => $this->activeConnections,
            'avg_latency'  => round($this->averageLatencyMs, 2),
        ];
    }
}
