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

namespace NetFusion\Core\DisasterRecovery\Cluster;

use JsonSerializable;

/**
 * Class ClusterNode
 *
 * Represents a regional disaster recovery node in the multi-region topology.
 */
class ClusterNode implements JsonSerializable
{
    public const ROLE_PRIMARY = 'PRIMARY';
    public const ROLE_STANDBY = 'STANDBY';
    public const ROLE_WITNESS = 'WITNESS';

    public const STATUS_ONLINE   = 'ONLINE';
    public const STATUS_DEGRADED = 'DEGRADED';
    public const STATUS_FENCED   = 'FENCED';
    public const STATUS_OFFLINE  = 'OFFLINE';

    public function __construct(
        public readonly string $nodeId,
        public readonly string $region,
        public string $role = self::ROLE_STANDBY,
        public string $status = self::STATUS_ONLINE,
        public string $endpoint = 'http://127.0.0.1:8000',
        public float $lastHeartbeat = 0.0,
        public float $replicationLagSec = 0.0,
        public int $fencingEpoch = 1
    ) {
        if ($this->lastHeartbeat === 0.0) {
            $this->lastHeartbeat = microtime(true);
        }
    }

    public function isPrimary(): bool
    {
        return $this->role === self::ROLE_PRIMARY && $this->status === self::STATUS_ONLINE;
    }

    public function isStandby(): bool
    {
        return $this->role === self::ROLE_STANDBY && $this->status === self::STATUS_ONLINE;
    }

    public function isFenced(): bool
    {
        return $this->status === self::STATUS_FENCED;
    }

    public function isHealthy(float $maxLagSec = 10.0, float $heartbeatTimeoutSec = 15.0): bool
    {
        if ($this->status !== self::STATUS_ONLINE) {
            return false;
        }

        $now = microtime(true);
        if (($now - $this->lastHeartbeat) > $heartbeatTimeoutSec) {
            return false;
        }

        if ($this->role === self::ROLE_STANDBY && $this->replicationLagSec > $maxLagSec) {
            return false;
        }

        return true;
    }

    public function updateHeartbeat(?float $now = null): void
    {
        $this->lastHeartbeat = $now ?? microtime(true);
        if ($this->status === self::STATUS_OFFLINE) {
            $this->status = self::STATUS_ONLINE;
        }
    }

    protected ?string $previousRole = null;

    public function fence(): void
    {
        $this->status = self::STATUS_FENCED;
        $this->previousRole = $this->role;
        if ($this->role === self::ROLE_PRIMARY) {
            $this->role = self::ROLE_STANDBY;
        }
    }

    public function unfence(): void
    {
        $this->status = self::STATUS_ONLINE;
        if ($this->previousRole !== null) {
            $this->role = $this->previousRole;
            $this->previousRole = null;
        }
    }

    public function toArray(): array
    {
        return [
            'node_id'             => $this->nodeId,
            'region'              => $this->region,
            'role'                => $this->role,
            'status'              => $this->status,
            'endpoint'            => $this->endpoint,
            'last_heartbeat'      => $this->lastHeartbeat,
            'replication_lag_sec' => $this->replicationLagSec,
            'fencing_epoch'       => $this->fencingEpoch,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
