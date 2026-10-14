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

namespace NetFusion\Core\Scheduling\Cluster;

use NetFusion\Core\Scheduling\Contracts\ScheduleStoreInterface;

class LeaderElection
{
    private ScheduleStoreInterface $store;
    private string $nodeId;
    private bool $isLeader = false;

    public function __construct(ScheduleStoreInterface $store, ?string $nodeId = null)
    {
        $this->store = $store;
        $this->nodeId = $nodeId ?? (gethostname() ?: 'node') . '_' . getmypid() . '_' . bin2hex(random_bytes(4));
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function isLeader(): bool
    {
        return $this->isLeader;
    }

    /**
     * Attempt to acquire leadership lock
     */
    public function tryAcquireLeader(int $ttlSeconds = 30): bool
    {
        $acquired = $this->store->acquireLeaderLock($this->nodeId, $ttlSeconds);
        $this->isLeader = $acquired;
        return $acquired;
    }

    /**
     * Renew heartbeat of leader lock
     */
    public function renew(int $ttlSeconds = 30): bool
    {
        if (!$this->isLeader) {
            return $this->tryAcquireLeader($ttlSeconds);
        }

        $renewed = $this->store->renewLeaderLock($this->nodeId, $ttlSeconds);
        $this->isLeader = $renewed;
        return $renewed;
    }

    /**
     * Release leadership lock voluntarily
     */
    public function release(): bool
    {
        if ($this->isLeader) {
            $released = $this->store->releaseLeaderLock($this->nodeId);
            $this->isLeader = false;
            return $released;
        }
        return true;
    }

    /**
     * Get currently active leader node ID
     */
    public function getLeaderNodeId(): ?string
    {
        return $this->store->getLeaderNodeId();
    }
}
