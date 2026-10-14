<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\ServiceMesh\Mesh;

use NetFusion\Core\ServiceMesh\Spiffe\SpiffeId;

/**
 * Class MeshNode
 *
 * Represents an individual service instance inside the Service Mesh.
 */
class MeshNode
{
    private string $status = 'HEALTHY'; // 'HEALTHY' | 'DEGRADED' | 'UNHEALTHY'
    private int $lastHeartbeat;

    public function __construct(
        private readonly string $nodeId,
        private readonly string $serviceName,
        private readonly SpiffeId $spiffeId,
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8080,
        private readonly int $mtlsPort = 8443,
        private readonly array $metadata = []
    ) {
        $this->lastHeartbeat = time();
    }

    public static function create(
        string $serviceName,
        string|SpiffeId $spiffeId,
        string $host = '127.0.0.1',
        int $port = 8080,
        int $mtlsPort = 8443,
        ?string $nodeId = null
    ): self {
        $id = $nodeId ?: "node-" . substr(md5($serviceName . $host . $port), 0, 8);
        $spiffe = $spiffeId instanceof SpiffeId ? $spiffeId : new SpiffeId($spiffeId);
        return new self($id, $serviceName, $spiffe, $host, $port, $mtlsPort);
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getSpiffeId(): SpiffeId
    {
        return $this->spiffeId;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getMtlsPort(): int
    {
        return $this->mtlsPort;
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

    public function heartbeat(): self
    {
        $this->lastHeartbeat = time();
        return $this;
    }

    public function getLastHeartbeat(): int
    {
        return $this->lastHeartbeat;
    }

    public function isAlive(int $heartbeatTimeoutSeconds = 30): bool
    {
        return (time() - $this->lastHeartbeat) <= $heartbeatTimeoutSeconds;
    }

    public function toArray(): array
    {
        return [
            'node_id' => $this->nodeId,
            'service_name' => $this->serviceName,
            'spiffe_id' => (string) $this->spiffeId,
            'host' => $this->host,
            'port' => $this->port,
            'mtls_port' => $this->mtlsPort,
            'status' => $this->status,
            'last_heartbeat' => $this->lastHeartbeat,
            'metadata' => $this->metadata,
        ];
    }
}
