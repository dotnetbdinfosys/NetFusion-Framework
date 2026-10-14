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

namespace NetFusion\Core\DistributedDb\Raft;

/**
 * Raft cluster orchestrator managing multi-node topology, RPC routing, and quorum.
 */
class RaftCluster
{
    /**
     * @var array<string, RaftNode>
     */
    private array $nodes = [];

    /**
     * @var array<string, array<string, bool>> Blocked peer-to-peer communication for partition testing
     */
    private array $networkPartitions = [];

    /**
     * @param array<RaftNode> $nodes Initial cluster nodes
     */
    public function __construct(array $nodes = [])
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
    }

    public function addNode(RaftNode $node): self
    {
        $this->nodes[$node->getNodeId()] = $node;
        return $this;
    }

    public function getNode(string $nodeId): ?RaftNode
    {
        return $this->nodes[$nodeId] ?? null;
    }

    /**
     * @return array<string, RaftNode>
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
     * Calculate majority quorum threshold: floor(N / 2) + 1.
     */
    public function getQuorum(): int
    {
        $n = count($this->nodes);
        if ($n === 0) {
            return 1;
        }

        return (int) (floor($n / 2) + 1);
    }

    /**
     * Get current cluster leader, if any.
     */
    public function getLeader(): ?RaftNode
    {
        foreach ($this->nodes as $node) {
            if ($node->getRole() === RaftRole::LEADER) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Elect a leader by executing a full election cycle.
     */
    public function electLeader(?string $candidateId = null): ?RaftNode
    {
        if (empty($this->nodes)) {
            return null;
        }

        // If candidateId not specified, choose first node or node with highest term
        if ($candidateId === null || !isset($this->nodes[$candidateId])) {
            $candidateId = array_key_first($this->nodes);
        }

        $candidate = $this->nodes[$candidateId];
        $voteRpc = $candidate->becomeCandidate();
        $quorum = $this->getQuorum();
        $allPeerIds = array_keys($this->nodes);

        // Broadcast RequestVote to all other reachable nodes
        foreach ($this->nodes as $peerId => $peerNode) {
            if ($peerId === $candidateId) {
                continue;
            }

            if ($this->isPartitioned($candidateId, $peerId)) {
                continue;
            }

            $response = $peerNode->handleRequestVote($voteRpc);
            if ($response->voteGranted) {
                $candidate->recordVote($peerId, true, $quorum);
            }
        }

        // If candidate achieved quorum, transition to LEADER
        if ($candidate->recordVote($candidateId, true, $quorum)) {
            $candidate->becomeLeader($allPeerIds);
            // Broadcast initial empty AppendEntries (heartbeat)
            $this->broadcastHeartbeat($candidate);
            return $candidate;
        }

        return null;
    }

    /**
     * Propose a command to the cluster via leader.
     * Replicates across nodes and commits once replicated on a majority quorum.
     */
    public function propose(string $command, mixed $payload = null, string $clientRequestId = ''): LogEntry
    {
        $leader = $this->getLeader();
        if ($leader === null) {
            $leader = $this->electLeader();
            if ($leader === null) {
                throw new \RuntimeException("No leader could be elected in Raft cluster.");
            }
        }

        $entry = $leader->appendCommand($command, $payload, $clientRequestId);
        $quorum = $this->getQuorum();
        $allPeerIds = array_keys($this->nodes);
        $leaderId = $leader->getNodeId();

        // Broadcast AppendEntries to all followers
        $committed = false;
        foreach ($this->nodes as $peerId => $peerNode) {
            if ($peerId === $leaderId) {
                continue;
            }

            if ($this->isPartitioned($leaderId, $peerId)) {
                continue;
            }

            $rpc = $leader->createAppendEntriesFor($peerId);
            $response = $peerNode->handleAppendEntries($rpc);
            if ($leader->handleAppendEntriesResponse($peerId, $response, $quorum, $allPeerIds)) {
                $committed = true;
            }
        }

        // If committed on majority quorum, broadcast updated leaderCommit to followers
        if ($committed) {
            $this->broadcastHeartbeat($leader);
        }

        return $entry;
    }

    /**
     * Broadcast heartbeat from leader to all reachable followers.
     */
    public function broadcastHeartbeat(RaftNode $leader): void
    {
        $leaderId = $leader->getNodeId();
        $quorum = $this->getQuorum();
        $allPeerIds = array_keys($this->nodes);

        foreach ($this->nodes as $peerId => $peerNode) {
            if ($peerId === $leaderId) {
                continue;
            }

            if ($this->isPartitioned($leaderId, $peerId)) {
                continue;
            }

            $rpc = $leader->createAppendEntriesFor($peerId);
            $response = $peerNode->handleAppendEntries($rpc);
            $leader->handleAppendEntriesResponse($peerId, $response, $quorum, $allPeerIds);
        }
    }

    /**
     * Partition the network between two sets of nodes.
     *
     * @param array<string> $partitionA
     * @param array<string> $partitionB
     */
    public function partition(array $partitionA, array $partitionB): void
    {
        foreach ($partitionA as $a) {
            foreach ($partitionB as $b) {
                $this->networkPartitions[$a][$b] = true;
                $this->networkPartitions[$b][$a] = true;
            }
        }
    }

    /**
     * Heal all network partitions.
     */
    public function healNetwork(): void
    {
        $this->networkPartitions = [];
    }

    public function isPartitioned(string $fromNode, string $toNode): bool
    {
        return !empty($this->networkPartitions[$fromNode][$toNode]);
    }
}
