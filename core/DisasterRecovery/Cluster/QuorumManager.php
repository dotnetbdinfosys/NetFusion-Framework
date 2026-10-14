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

use RuntimeException;

/**
 * Class QuorumManager
 *
 * Coordinates multi-region consensus and split-brain prevention:
 * Requires strict majority (N/2 + 1) for primary election and fencing.
 */
class QuorumManager
{
    /**
     * @var array<string, ClusterNode> nodeId => ClusterNode
     */
    protected array $nodes = [];

    protected int $currentEpoch = 1;
    protected ?string $primaryNodeId = null;

    public function __construct(int $initialEpoch = 1)
    {
        $this->currentEpoch = $initialEpoch;
    }

    public function registerNode(ClusterNode $node): self
    {
        $this->nodes[$node->nodeId] = $node;
        if ($node->role === ClusterNode::ROLE_PRIMARY) {
            $this->primaryNodeId = $node->nodeId;
        }
        return $this;
    }

    public function getNode(string $nodeId): ?ClusterNode
    {
        return $this->nodes[$nodeId] ?? null;
    }

    /**
     * @return array<string, ClusterNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getNodeCount(): int
    {
        return count($this->nodes);
    }

    /**
     * Calculate strict majority quorum threshold: floor(N / 2) + 1.
     */
    public function getQuorumThreshold(): int
    {
        $n = count($this->nodes);
        if ($n === 0) {
            return 1;
        }
        return (int) floor($n / 2) + 1;
    }

    public function getCurrentEpoch(): int
    {
        return $this->currentEpoch;
    }

    public function getPrimaryNode(): ?ClusterNode
    {
        return $this->primaryNodeId !== null ? ($this->nodes[$this->primaryNodeId] ?? null) : null;
    }

    /**
     * Count currently online and reachable nodes.
     */
    public function getOnlineNodeCount(): int
    {
        $cnt = 0;
        foreach ($this->nodes as $node) {
            if ($node->status === ClusterNode::STATUS_ONLINE) {
                $cnt++;
            }
        }
        return $cnt;
    }

    /**
     * Check if the cluster currently has a healthy quorum.
     */
    public function hasQuorum(): bool
    {
        return $this->getOnlineNodeCount() >= $this->getQuorumThreshold();
    }

    /**
     * Attempt failover and election of a new Primary node.
     *
     * @param string $candidateNodeId
     * @param bool $force Bypass quorum check (emergency only)
     * @return array{success: bool, votes: int, quorum: int, epoch: int, fencing_token: ?FencingToken, reason: string}
     */
    public function electPrimary(string $candidateNodeId, bool $force = false): array
    {
        $candidate = $this->getNode($candidateNodeId);
        if ($candidate === null) {
            throw new RuntimeException("Candidate node '{$candidateNodeId}' not registered in cluster");
        }

        $threshold = $this->getQuorumThreshold();
        $votes = 0;

        // Gather votes from online nodes
        foreach ($this->nodes as $node) {
            if ($node->status === ClusterNode::STATUS_ONLINE) {
                $votes++;
            }
        }

        // Quorum validation
        if (!$force && $votes < $threshold) {
            return [
                'success'       => false,
                'votes'         => $votes,
                'quorum'        => $threshold,
                'epoch'         => $this->currentEpoch,
                'fencing_token' => null,
                'reason'        => "Quorum not reached: {$votes}/{$threshold} votes obtained. Split-brain prevented!",
            ];
        }

        // 1. Fence the former primary node to prevent split-brain writes
        if ($this->primaryNodeId !== null && $this->primaryNodeId !== $candidateNodeId) {
            $formerPrimary = $this->getNode($this->primaryNodeId);
            if ($formerPrimary !== null) {
                $formerPrimary->fence();
            }
        }

        // 2. Increment cluster epoch and issue new Fencing Token
        $this->currentEpoch++;
        $token = new FencingToken($this->currentEpoch, $candidateNodeId, microtime(true));

        // 3. Promote candidate
        $candidate->role = ClusterNode::ROLE_PRIMARY;
        $candidate->status = ClusterNode::STATUS_ONLINE;
        $candidate->fencingEpoch = $this->currentEpoch;
        $this->primaryNodeId = $candidateNodeId;

        return [
            'success'       => true,
            'votes'         => $votes,
            'quorum'        => $threshold,
            'epoch'         => $this->currentEpoch,
            'fencing_token' => $token,
            'reason'        => "Candidate '{$candidateNodeId}' successfully elected as Primary in epoch {$this->currentEpoch}",
        ];
    }
}
