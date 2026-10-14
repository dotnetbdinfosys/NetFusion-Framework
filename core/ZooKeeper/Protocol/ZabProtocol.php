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

namespace NetFusion\Core\ZooKeeper\Protocol;

use NetFusion\Core\ZooKeeper\Enums\ZabState;

/**
 * ZooKeeper Atomic Broadcast (ZAB) Protocol Engine.
 * Manages 64-bit zxid (epoch + counter), Fast Leader Election (FLE), proposal replication, and quorum commits.
 */
class ZabProtocol
{
    protected int $serverId;
    protected int $epoch = 1;
    protected int $counter = 0;
    protected ZabState $state = ZabState::LOOKING;
    protected ?int $currentLeader = null;
    /** @var array<int, array{zxid: int, epoch: int, counter: int}> Cluster peer vote records */
    protected array $votes = [];
    /** @var array<int, array{zxid: int, op: string, path: string, data: ?string, acks: array<int, bool>}> */
    protected array $proposals = [];
    protected int $clusterSize;

    public function __construct(int $serverId = 1, int $clusterSize = 3)
    {
        $this->serverId = $serverId;
        $this->clusterSize = max(1, $clusterSize);
    }

    public function getServerId(): int
    {
        return $this->serverId;
    }

    public function getEpoch(): int
    {
        return $this->epoch;
    }

    public function getState(): ZabState
    {
        return $this->state;
    }

    public function getCurrentLeader(): ?int
    {
        return $this->currentLeader;
    }

    /**
     * Compute 64-bit zxid combining 32-bit epoch and 32-bit counter.
     */
    public function nextZxid(): int
    {
        $this->counter++;
        return ($this->epoch << 32) | ($this->counter & 0xFFFFFFFF);
    }

    public function getLastZxid(): int
    {
        return ($this->epoch << 32) | ($this->counter & 0xFFFFFFFF);
    }

    /**
     * Fast Leader Election (FLE) ballot generation.
     *
     * @return array{serverId: int, zxid: int, epoch: int}
     */
    public function createVote(): array
    {
        return [
            'serverId' => $this->serverId,
            'zxid' => $this->getLastZxid(),
            'epoch' => $this->epoch,
        ];
    }

    /**
     * Compare two votes according to ZAB rules:
     * 1. Higher epoch wins
     * 2. Higher zxid wins
     * 3. Higher serverId breaks tie
     */
    public function isVoteBetter(array $candidate, array $current): bool
    {
        if ($candidate['epoch'] > $current['epoch']) {
            return true;
        }
        if ($candidate['epoch'] < $current['epoch']) {
            return false;
        }

        if ($candidate['zxid'] > $current['zxid']) {
            return true;
        }
        if ($candidate['zxid'] < $current['zxid']) {
            return false;
        }

        return $candidate['serverId'] > $current['serverId'];
    }

    /**
     * Process received vote and evaluate quorum.
     * Quorum threshold = floor(clusterSize / 2) + 1.
     */
    public function castVote(int $fromServerId, array $vote): ?int
    {
        $this->votes[$fromServerId] = $vote;

        // Group by voted leader
        $voteCounts = [];
        foreach ($this->votes as $v) {
            $candLeader = $v['serverId'];
            $voteCounts[$candLeader] = ($voteCounts[$candLeader] ?? 0) + 1;
        }

        $quorum = (int)floor($this->clusterSize / 2) + 1;
        foreach ($voteCounts as $leaderId => $count) {
            if ($count >= $quorum) {
                $this->currentLeader = $leaderId;
                if ($leaderId === $this->serverId) {
                    $this->state = ZabState::LEADING;
                    $this->epoch++;
                    $this->counter = 0;
                } else {
                    $this->state = ZabState::FOLLOWING;
                }
                return $leaderId;
            }
        }

        return null;
    }

    /**
     * Leader creates a proposal for a write operation.
     */
    public function propose(string $op, string $path, ?string $data = null): int
    {
        $zxid = $this->nextZxid();
        $this->proposals[$zxid] = [
            'zxid' => $zxid,
            'op' => $op,
            'path' => $path,
            'data' => $data,
            'acks' => [$this->serverId => true], // Leader self-acks
        ];
        return $zxid;
    }

    /**
     * Follower or peer acknowledges proposal.
     * Returns true if quorum reached and operation committed.
     */
    public function acknowledge(int $zxid, int $peerId): bool
    {
        if (!isset($this->proposals[$zxid])) {
            return false;
        }

        $this->proposals[$zxid]['acks'][$peerId] = true;
        $quorum = (int)floor($this->clusterSize / 2) + 1;

        return count($this->proposals[$zxid]['acks']) >= $quorum;
    }

    /**
     * Check if proposal has reached quorum.
     */
    public function isCommitted(int $zxid): bool
    {
        if (!isset($this->proposals[$zxid])) {
            return false;
        }
        $quorum = (int)floor($this->clusterSize / 2) + 1;
        return count($this->proposals[$zxid]['acks']) >= $quorum;
    }

    public function clear(): void
    {
        $this->votes = [];
        $this->proposals = [];
        $this->state = ZabState::LOOKING;
        $this->currentLeader = null;
    }
}
