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

namespace NetFusion\Core\ZooKeeper\Session;

/**
 * Client session tracking and heartbeat timeout handling.
 * Ephemeral nodes created under a session are automatically deleted when the session expires or is closed.
 */
class Session
{
    protected int $id;
    protected int $timeout; // milliseconds
    protected float $lastHeartbeat;
    protected string $password;
    protected bool $closed = false;
    /** @var array<string, bool> [path => true] */
    protected array $ephemeralNodes = [];

    public function __construct(int $id = 0, int $timeout = 30000, string $password = '')
    {
        $this->id = $id > 0 ? $id : random_int(100000, 999999);
        $this->timeout = $timeout;
        $this->lastHeartbeat = microtime(true);
        $this->password = $password !== '' ? $password : bin2hex(random_bytes(16));
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function touch(): void
    {
        $this->lastHeartbeat = microtime(true);
    }

    public function getLastHeartbeat(): float
    {
        return $this->lastHeartbeat;
    }

    public function isExpired(float $customTime = 0.0): bool
    {
        if ($this->closed) {
            return true;
        }
        $now = $customTime > 0.0 ? $customTime : microtime(true);
        return (($now - $this->lastHeartbeat) * 1000) > $this->timeout;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function registerEphemeralNode(string $path): void
    {
        $this->ephemeralNodes[$path] = true;
    }

    public function unregisterEphemeralNode(string $path): void
    {
        unset($this->ephemeralNodes[$path]);
    }

    /**
     * @return array<string>
     */
    public function getEphemeralNodes(): array
    {
        return array_keys($this->ephemeralNodes);
    }
}
