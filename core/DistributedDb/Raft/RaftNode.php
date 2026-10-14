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
 * Replicated state machine node executing the Raft distributed consensus algorithm.
 */
class RaftNode
{
    private string $nodeId;
    private RaftRole $role;
    private int $currentTerm = 0;
    private ?string $votedFor = null;

    /**
     * @var array<int, LogEntry> 1-indexed log entries
     */
    private array $log = [];

    private int $commitIndex = 0;
    private int $lastApplied = 0;

    /**
     * @var array<string, int> Next log index to send to each peer (leader only)
     */
    private array $nextIndex = [];

    /**
     * @var array<string, int> Highest log index known to be replicated on peer (leader only)
     */
    private array $matchIndex = [];

    /**
     * @var array<string, bool> Voter IDs who granted vote in current election
     */
    private array $votesReceived = [];

    /**
     * @var array<string, mixed> Key-value state machine
     */
    private array $stateMachine = [];

    private float $lastHeartbeatTime = 0.0;

    public function __construct(string $nodeId)
    {
        $this->nodeId = $nodeId;
        $this->role = RaftRole::FOLLOWER;
        $this->lastHeartbeatTime = microtime(true);
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getRole(): RaftRole
    {
        return $this->role;
    }

    public function getCurrentTerm(): int
    {
        return $this->currentTerm;
    }

    public function getVotedFor(): ?string
    {
        return $this->votedFor;
    }

    public function getCommitIndex(): int
    {
        return $this->commitIndex;
    }

    public function getLastApplied(): int
    {
        return $this->lastApplied;
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getLog(): array
    {
        return $this->log;
    }

    public function getLastLogIndex(): int
    {
        if (empty($this->log)) {
            return 0;
        }

        return max(array_keys($this->log));
    }

    public function getLastLogTerm(): int
    {
        $lastIndex = $this->getLastLogIndex();
        if ($lastIndex === 0 || !isset($this->log[$lastIndex])) {
            return 0;
        }

        return $this->log[$lastIndex]->term;
    }

    public function getEntry(int $index): ?LogEntry
    {
        return $this->log[$index] ?? null;
    }

    public function getStateMachine(): array
    {
        return $this->stateMachine;
    }

    public function getStateValue(string $key, mixed $default = null): mixed
    {
        return $this->stateMachine[$key] ?? $default;
    }

    public function getNextIndexFor(string $peerId): int
    {
        return $this->nextIndex[$peerId] ?? ($this->getLastLogIndex() + 1);
    }

    public function getMatchIndexFor(string $peerId): int
    {
        return $this->matchIndex[$peerId] ?? 0;
    }

    /**
     * Transition to FOLLOWER role.
     */
    public function becomeFollower(int $term): void
    {
        $this->role = RaftRole::FOLLOWER;
        $this->currentTerm = $term;
        $this->votedFor = null;
        $this->votesReceived = [];
        $this->lastHeartbeatTime = microtime(true);
    }

    /**
     * Transition to CANDIDATE role and generate RequestVote RPC.
     */
    public function becomeCandidate(): RequestVoteRpc
    {
        $this->role = RaftRole::CANDIDATE;
        $this->currentTerm++;
        $this->votedFor = $this->nodeId;
        $this->votesReceived = [$this->nodeId => true];
        $this->lastHeartbeatTime = microtime(true);

        return new RequestVoteRpc(
            term: $this->currentTerm,
            candidateId: $this->nodeId,
            lastLogIndex: $this->getLastLogIndex(),
            lastLogTerm: $this->getLastLogTerm()
        );
    }

    /**
     * Transition to LEADER role upon acquiring majority quorum votes.
     *
     * @param array<string> $peerIds
     */
    public function becomeLeader(array $peerIds): void
    {
        $this->role = RaftRole::LEADER;
        $this->nextIndex = [];
        $this->matchIndex = [];

        $lastLogIndex = $this->getLastLogIndex();
        foreach ($peerIds as $peerId) {
            $this->nextIndex[$peerId] = $lastLogIndex + 1;
            $this->matchIndex[$peerId] = 0;
        }

        $this->matchIndex[$this->nodeId] = $lastLogIndex;
    }

    /**
     * Record a vote from peer node. If quorum reached, returns true.
     */
    public function recordVote(string $voterId, bool $granted, int $quorum): bool
    {
        if ($this->role !== RaftRole::CANDIDATE || !$granted) {
            return false;
        }

        $this->votesReceived[$voterId] = true;
        return count($this->votesReceived) >= $quorum;
    }

    /**
     * Handle incoming RequestVote RPC.
     */
    public function handleRequestVote(RequestVoteRpc $rpc): RequestVoteResponse
    {
        // 1. Reply false if term < currentTerm
        if ($rpc->term < $this->currentTerm) {
            return new RequestVoteResponse(
                term: $this->currentTerm,
                voteGranted: false,
                voterId: $this->nodeId
            );
        }

        // If RPC term is higher, step down to follower
        if ($rpc->term > $this->currentTerm) {
            $this->becomeFollower($rpc->term);
        }

        // 2. Check if votedFor is null or candidateId, and candidate's log is up-to-date
        $canVote = ($this->votedFor === null || $this->votedFor === $rpc->candidateId);
        $candidateUpToDate = $this->isCandidateLogUpToDate($rpc->lastLogIndex, $rpc->lastLogTerm);

        if ($canVote && $candidateUpToDate) {
            $this->votedFor = $rpc->candidateId;
            $this->lastHeartbeatTime = microtime(true);

            return new RequestVoteResponse(
                term: $this->currentTerm,
                voteGranted: true,
                voterId: $this->nodeId
            );
        }

        return new RequestVoteResponse(
            term: $this->currentTerm,
            voteGranted: false,
            voterId: $this->nodeId
        );
    }

    /**
     * Handle incoming AppendEntries RPC.
     */
    public function handleAppendEntries(AppendEntriesRpc $rpc): AppendEntriesResponse
    {
        // 1. Reply false if term < currentTerm
        if ($rpc->term < $this->currentTerm) {
            return new AppendEntriesResponse(
                term: $this->currentTerm,
                success: false,
                matchIndex: $this->getLastLogIndex(),
                nodeId: $this->nodeId
            );
        }

        // If RPC term is higher or equal, acknowledge leader and step down
        if ($rpc->term > $this->currentTerm || $this->role !== RaftRole::FOLLOWER) {
            $this->becomeFollower($rpc->term);
        }

        $this->lastHeartbeatTime = microtime(true);

        // 2. Reply false if log doesn't contain an entry at prevLogIndex matching prevLogTerm
        if ($rpc->prevLogIndex > 0) {
            if (!isset($this->log[$rpc->prevLogIndex])) {
                return new AppendEntriesResponse(
                    term: $this->currentTerm,
                    success: false,
                    matchIndex: $this->getLastLogIndex(),
                    nodeId: $this->nodeId
                );
            }

            if ($this->log[$rpc->prevLogIndex]->term !== $rpc->prevLogTerm) {
                return new AppendEntriesResponse(
                    term: $this->currentTerm,
                    success: false,
                    matchIndex: $this->getLastLogIndex(),
                    nodeId: $this->nodeId
                );
            }
        }

        // 3. If an existing entry conflicts with a new one, delete existing and all that follow
        $insertIndex = $rpc->prevLogIndex + 1;
        foreach ($rpc->entries as $newEntry) {
            if (isset($this->log[$insertIndex])) {
                if ($this->log[$insertIndex]->term !== $newEntry->term) {
                    // Truncate conflicting suffix
                    $lastIdx = $this->getLastLogIndex();
                    for ($i = $insertIndex; $i <= $lastIdx; $i++) {
                        unset($this->log[$i]);
                    }
                    $this->log[$insertIndex] = $newEntry;
                }
            } else {
                $this->log[$insertIndex] = $newEntry;
            }
            $insertIndex++;
        }

        // 4. Update commitIndex
        if ($rpc->leaderCommit > $this->commitIndex) {
            $this->commitIndex = min($rpc->leaderCommit, $this->getLastLogIndex());
            $this->applyCommittedEntries();
        }

        return new AppendEntriesResponse(
            term: $this->currentTerm,
            success: true,
            matchIndex: $this->getLastLogIndex(),
            nodeId: $this->nodeId
        );
    }

    /**
     * Append a new command to local log (Leader only).
     */
    public function appendCommand(string $command, mixed $payload = null, string $clientRequestId = ''): LogEntry
    {
        if ($this->role !== RaftRole::LEADER) {
            throw new \RuntimeException("Node {$this->nodeId} cannot append command: not cluster leader.");
        }

        $newIndex = $this->getLastLogIndex() + 1;
        $entry = new LogEntry(
            term: $this->currentTerm,
            index: $newIndex,
            command: $command,
            payload: $payload,
            timestamp: time(),
            clientRequestId: $clientRequestId
        );

        $this->log[$newIndex] = $entry;
        $this->matchIndex[$this->nodeId] = $newIndex;

        return $entry;
    }

    /**
     * Handle AppendEntries response on the leader node.
     * Checks if majority quorum has replicated entries to advance commitIndex.
     */
    public function handleAppendEntriesResponse(
        string $followerId,
        AppendEntriesResponse $response,
        int $quorum,
        array $allPeerIds
    ): bool {
        if ($this->role !== RaftRole::LEADER) {
            return false;
        }

        if ($response->term > $this->currentTerm) {
            $this->becomeFollower($response->term);
            return false;
        }

        if ($response->success) {
            $this->matchIndex[$followerId] = max($this->matchIndex[$followerId] ?? 0, $response->matchIndex);
            $this->nextIndex[$followerId] = $this->matchIndex[$followerId] + 1;

            // Check if there is an N > commitIndex such that majority of matchIndex[i] >= N
            // and log[N]->term === currentTerm
            $lastIndex = $this->getLastLogIndex();
            for ($N = $lastIndex; $N > $this->commitIndex; $N--) {
                if (isset($this->log[$N]) && $this->log[$N]->term === $this->currentTerm) {
                    $matchCount = 1; // leader itself matches
                    foreach ($allPeerIds as $peerId) {
                        if ($peerId !== $this->nodeId && ($this->matchIndex[$peerId] ?? 0) >= $N) {
                            $matchCount++;
                        }
                    }

                    if ($matchCount >= $quorum) {
                        $this->commitIndex = $N;
                        $this->applyCommittedEntries();
                        return true;
                    }
                }
            }
        } else {
            // Decrement nextIndex and retry
            $this->nextIndex[$followerId] = max(1, ($this->nextIndex[$followerId] ?? 2) - 1);
        }

        return false;
    }

    /**
     * Create AppendEntriesRpc for a specific peer.
     */
    public function createAppendEntriesFor(string $peerId): AppendEntriesRpc
    {
        $prevLogIndex = $this->getNextIndexFor($peerId) - 1;
        $prevLogTerm = 0;
        if ($prevLogIndex > 0 && isset($this->log[$prevLogIndex])) {
            $prevLogTerm = $this->log[$prevLogIndex]->term;
        }

        $entries = [];
        $lastLogIndex = $this->getLastLogIndex();
        for ($i = $prevLogIndex + 1; $i <= $lastLogIndex; $i++) {
            if (isset($this->log[$i])) {
                $entries[] = $this->log[$i];
            }
        }

        return new AppendEntriesRpc(
            term: $this->currentTerm,
            leaderId: $this->nodeId,
            prevLogIndex: $prevLogIndex,
            prevLogTerm: $prevLogTerm,
            entries: $entries,
            leaderCommit: $this->commitIndex
        );
    }

    /**
     * Apply all committed entries to the state machine up to commitIndex.
     *
     * @return array<LogEntry> Applied entries
     */
    public function applyCommittedEntries(): array
    {
        $applied = [];

        while ($this->lastApplied < $this->commitIndex) {
            $this->lastApplied++;
            if (isset($this->log[$this->lastApplied])) {
                $entry = $this->log[$this->lastApplied];
                $this->executeOnStateMachine($entry);
                $applied[] = $entry;
            }
        }

        return $applied;
    }

    /**
     * Execute entry command on the in-memory state machine.
     */
    private function executeOnStateMachine(LogEntry $entry): void
    {
        if (str_starts_with($entry->command, 'SET:')) {
            $key = substr($entry->command, 4);
            $this->stateMachine[$key] = $entry->payload;
        } elseif (str_starts_with($entry->command, 'DEL:')) {
            $key = substr($entry->command, 4);
            unset($this->stateMachine[$key]);
        } elseif (str_starts_with($entry->command, 'SQL:')) {
            // Key-value or table store
            if (is_array($entry->payload) && isset($entry->payload['table'], $entry->payload['row'])) {
                $table = (string) $entry->payload['table'];
                $pk = $entry->payload['pk'] ?? (string) uniqid('row_');
                $this->stateMachine['db'][$table][$pk] = $entry->payload['row'];
            }
        }
    }

    /**
     * Verify whether candidate's log is at least as up-to-date as receiver's log.
     */
    private function isCandidateLogUpToDate(int $candidateLastIndex, int $candidateLastTerm): bool
    {
        $myLastTerm = $this->getLastLogTerm();
        $myLastIndex = $this->getLastLogIndex();

        if ($candidateLastTerm !== $myLastTerm) {
            return $candidateLastTerm > $myLastTerm;
        }

        return $candidateLastIndex >= $myLastIndex;
    }
}
