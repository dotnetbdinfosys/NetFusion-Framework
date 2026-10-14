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

use JsonSerializable;

/**
 * AppendEntries RPC invoked by leader to replicate log entries and serve as heartbeat.
 */
class AppendEntriesRpc implements JsonSerializable
{
    /**
     * @param int $term Leader's current term
     * @param string $leaderId Leader identifier
     * @param int $prevLogIndex Index of log entry immediately preceding new ones
     * @param int $prevLogTerm Term of prevLogIndex entry
     * @param array<LogEntry> $entries Log entries to store (empty for heartbeat)
     * @param int $leaderCommit Leader's commitIndex
     */
    public function __construct(
        public readonly int $term,
        public readonly string $leaderId,
        public readonly int $prevLogIndex,
        public readonly int $prevLogTerm,
        public readonly array $entries = [],
        public readonly int $leaderCommit = 0
    ) {
    }

    public function isHeartbeat(): bool
    {
        return empty($this->entries);
    }

    public function toArray(): array
    {
        return [
            'term' => $this->term,
            'leader_id' => $this->leaderId,
            'prev_log_index' => $this->prevLogIndex,
            'prev_log_term' => $this->prevLogTerm,
            'entries' => array_map(fn(LogEntry $e) => $e->toArray(), $this->entries),
            'leader_commit' => $this->leaderCommit,
        ];
    }

    public static function fromArray(array $data): self
    {
        $entries = [];
        if (!empty($data['entries']) && is_array($data['entries'])) {
            foreach ($data['entries'] as $entryData) {
                $entries[] = $entryData instanceof LogEntry ? $entryData : LogEntry::fromArray($entryData);
            }
        }

        return new self(
            term: (int) ($data['term'] ?? 0),
            leaderId: (string) ($data['leader_id'] ?? ''),
            prevLogIndex: (int) ($data['prev_log_index'] ?? 0),
            prevLogTerm: (int) ($data['prev_log_term'] ?? 0),
            entries: $entries,
            leaderCommit: (int) ($data['leader_commit'] ?? 0)
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
