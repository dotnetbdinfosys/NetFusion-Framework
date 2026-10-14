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

/**
 * Class HeartbeatMonitor
 *
 * Tracks regional node liveness and triggers automated failover alerts.
 */
class HeartbeatMonitor
{
    /**
     * @var array<string, float> nodeId => last heartbeat timestamp
     */
    protected array $heartbeats = [];

    public function recordHeartbeat(string $nodeId, ?float $timestamp = null): void
    {
        $this->heartbeats[$nodeId] = $timestamp ?? microtime(true);
    }

    public function getLastHeartbeat(string $nodeId): ?float
    {
        return $this->heartbeats[$nodeId] ?? null;
    }

    public function isAlive(string $nodeId, float $timeoutSeconds = 5.0, ?float $now = null): bool
    {
        $last = $this->heartbeats[$nodeId] ?? null;
        if ($last === null) {
            return false;
        }

        $currentTime = $now ?? microtime(true);
        return ($currentTime - $last) <= $timeoutSeconds;
    }

    /**
     * Check if the current Primary node in QuorumManager has died.
     */
    public function isPrimaryUnresponsive(QuorumManager $quorum, float $timeoutSeconds = 5.0, ?float $now = null): bool
    {
        $primary = $quorum->getPrimaryNode();
        if ($primary === null) {
            return true;
        }

        return !$this->isAlive($primary->nodeId, $timeoutSeconds, $now);
    }
}
