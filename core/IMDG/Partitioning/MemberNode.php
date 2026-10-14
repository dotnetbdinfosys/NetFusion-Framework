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

namespace NetFusion\Core\IMDG\Partitioning;

class MemberNode
{
    public const STATUS_ALIVE = 'ALIVE';
    public const STATUS_SUSPECT = 'SUSPECT';
    public const STATUS_DEAD = 'DEAD';

    protected string $id;
    protected string $host;
    protected int $port;
    protected int $weight;
    protected bool $isLocal;
    protected string $status;
    protected float $lastHeartbeat;
    /** @var array<string, mixed> */
    protected array $attributes;

    public function __construct(
        string $id,
        string $host = '127.0.0.1',
        int $port = 5701,
        int $weight = 1,
        bool $isLocal = false,
        string $status = self::STATUS_ALIVE,
        array $attributes = []
    ) {
        $this->id = $id;
        $this->host = $host;
        $this->port = $port;
        $this->weight = max(1, $weight);
        $this->isLocal = $isLocal;
        $this->status = $status;
        $this->lastHeartbeat = microtime(true);
        $this->attributes = $attributes;
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

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function isLocal(): bool
    {
        return $this->isLocal;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isAlive(): bool
    {
        return $this->status === self::STATUS_ALIVE;
    }

    public function getLastHeartbeat(): float
    {
        return $this->lastHeartbeat;
    }

    public function beat(): self
    {
        $this->lastHeartbeat = microtime(true);
        $this->status = self::STATUS_ALIVE;
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAddress(): string
    {
        return "{$this->host}:{$this->port}";
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'host' => $this->host,
            'port' => $this->port,
            'weight' => $this->weight,
            'isLocal' => $this->isLocal,
            'status' => $this->status,
            'address' => $this->getAddress(),
            'lastHeartbeat' => $this->lastHeartbeat,
            'attributes' => $this->attributes,
        ];
    }
}
