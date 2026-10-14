<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/RPC/Registry/ServiceInstance.php
| Module    : RPC & Service Mesh
| Namespace : NetFusion\Core\RPC\Registry
| Purpose   : Value object representing a single microservice node instance in the mesh.
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

namespace NetFusion\Core\RPC\Registry;

use JsonSerializable;

class ServiceInstance implements JsonSerializable
{
    public const STATUS_UP   = 'UP';
    public const STATUS_DOWN = 'DOWN';

    public function __construct(
        public readonly string $id,
        public readonly string $serviceName,
        public readonly string $host,
        public readonly int    $port,
        public readonly array  $metadata = [],
        public string          $status = self::STATUS_UP,
        public ?float          $lastHeartbeat = null,
        public int             $activeConnections = 0
    ) {
        $this->lastHeartbeat ??= microtime(true);
    }

    public static function create(
        string $serviceName,
        string $host = '127.0.0.1',
        int $port = 9500,
        array $metadata = [],
        ?string $id = null
    ): self {
        $nodeId = $id ?? "{$serviceName}-" . bin2hex(random_bytes(4));
        return new self(
            id: $nodeId,
            serviceName: $serviceName,
            host: $host,
            port: $port,
            metadata: $metadata,
            status: self::STATUS_UP,
            lastHeartbeat: microtime(true)
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            serviceName: (string) ($data['service_name'] ?? $data['service'] ?? ''),
            host: (string) ($data['host'] ?? '127.0.0.1'),
            port: (int) ($data['port'] ?? 9500),
            metadata: (array) ($data['metadata'] ?? []),
            status: (string) ($data['status'] ?? self::STATUS_UP),
            lastHeartbeat: isset($data['last_heartbeat']) ? (float) $data['last_heartbeat'] : microtime(true),
            activeConnections: (int) ($data['active_connections'] ?? 0)
        );
    }

    public function isHealthy(int $ttlSeconds = 30): bool
    {
        if ($this->status !== self::STATUS_UP) {
            return false;
        }

        if ($this->lastHeartbeat === null) {
            return false;
        }

        return (microtime(true) - $this->lastHeartbeat) <= $ttlSeconds;
    }

    public function getEndpoint(): string
    {
        return "tcp://{$this->host}:{$this->port}";
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'service_name'       => $this->serviceName,
            'host'               => $this->host,
            'port'               => $this->port,
            'metadata'           => $this->metadata,
            'status'             => $this->status,
            'last_heartbeat'     => $this->lastHeartbeat,
            'active_connections' => $this->activeConnections,
            'endpoint'           => $this->getEndpoint(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
