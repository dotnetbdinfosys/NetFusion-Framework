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

namespace NetFusion\Core\ServiceMesh\Health;

use JsonSerializable;
use NetFusion\Core\ServiceMesh\Security\SpiffeId;

class ServiceInstance implements JsonSerializable
{
    public const STATUS_HEALTHY = 'HEALTHY';
    public const STATUS_DEGRADED = 'DEGRADED';
    public const STATUS_UNHEALTHY = 'UNHEALTHY';
    public const STATUS_DRAINED = 'DRAINED';
    public const STATUS_EJECTED = 'EJECTED';

    protected string $id;
    protected string $serviceName;
    protected string $host;
    protected int $port;
    protected string $subset;
    protected int $weight;
    protected string $status;
    protected ?SpiffeId $spiffeId = null;
    /** @var array<string, mixed> */
    protected array $metadata = [];

    // Runtime health & connection metrics
    protected int $activeConnections = 0;
    protected int $consecutiveErrors = 0;
    protected ?float $ejectedUntil = null;
    protected ?float $lastHealthCheck = null;

    /**
     * @param string $id
     * @param string $serviceName
     * @param string $host
     * @param int $port
     * @param string $subset
     * @param int $weight
     * @param string $status
     * @param SpiffeId|null $spiffeId
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $id,
        string $serviceName,
        string $host,
        int $port,
        string $subset = 'v1',
        int $weight = 100,
        string $status = self::STATUS_HEALTHY,
        ?SpiffeId $spiffeId = null,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->serviceName = $serviceName;
        $this->host = $host;
        $this->port = $port;
        $this->subset = $subset;
        $this->weight = max(1, $weight);
        $this->status = strtoupper($status);
        $this->spiffeId = $spiffeId ?? SpiffeId::of($serviceName);
        $this->metadata = $metadata;
    }

    public static function create(
        string $serviceName,
        string $host = '127.0.0.1',
        int $port = 8080,
        string $subset = 'v1',
        ?string $id = null
    ): self {
        $instanceId = $id ?? sprintf('%s-%s-%d-%s', $serviceName, $subset, $port, bin2hex(random_bytes(3)));
        return new self($instanceId, $serviceName, $host, $port, $subset);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getAddress(): string
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    public function getSubset(): string
    {
        return $this->subset;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = strtoupper($status);
        return $this;
    }

    public function getSpiffeId(): ?SpiffeId
    {
        return $this->spiffeId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getActiveConnections(): int
    {
        return $this->activeConnections;
    }

    public function incrementActiveConnections(): void
    {
        $this->activeConnections++;
    }

    public function decrementActiveConnections(): void
    {
        $this->activeConnections = max(0, $this->activeConnections - 1);
    }

    public function recordSuccess(): void
    {
        $this->consecutiveErrors = 0;
        if ($this->status === self::STATUS_DEGRADED) {
            $this->status = self::STATUS_HEALTHY;
        }
    }

    public function recordError(): int
    {
        $this->consecutiveErrors++;
        return $this->consecutiveErrors;
    }

    public function getConsecutiveErrors(): int
    {
        return $this->consecutiveErrors;
    }

    public function eject(float $durationSeconds): void
    {
        $this->status = self::STATUS_EJECTED;
        $this->ejectedUntil = microtime(true) + $durationSeconds;
    }

    public function restoreFromEjection(): void
    {
        $this->status = self::STATUS_HEALTHY;
        $this->consecutiveErrors = 0;
        $this->ejectedUntil = null;
    }

    public function isAvailable(): bool
    {
        if ($this->status === self::STATUS_EJECTED) {
            if ($this->ejectedUntil !== null && microtime(true) >= $this->ejectedUntil) {
                $this->restoreFromEjection();
                return true;
            }
            return false;
        }

        return $this->status === self::STATUS_HEALTHY || $this->status === self::STATUS_DEGRADED;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service_name' => $this->serviceName,
            'address' => $this->getAddress(),
            'host' => $this->host,
            'port' => $this->port,
            'subset' => $this->subset,
            'weight' => $this->weight,
            'status' => $this->status,
            'spiffe_id' => $this->spiffeId?->toString(),
            'active_connections' => $this->activeConnections,
            'consecutive_errors' => $this->consecutiveErrors,
            'is_available' => $this->isAvailable(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
