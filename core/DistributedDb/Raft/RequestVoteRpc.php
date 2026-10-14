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
 * RequestVote RPC invoked by candidates to gather votes in an election.
 */
class RequestVoteRpc implements JsonSerializable
{
    public function __construct(
        public readonly int $term,
        public readonly string $candidateId,
        public readonly int $lastLogIndex,
        public readonly int $lastLogTerm
    ) {
    }

    public function toArray(): array
    {
        return [
            'term' => $this->term,
            'candidate_id' => $this->candidateId,
            'last_log_index' => $this->lastLogIndex,
            'last_log_term' => $this->lastLogTerm,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            term: (int) ($data['term'] ?? 0),
            candidateId: (string) ($data['candidate_id'] ?? ''),
            lastLogIndex: (int) ($data['last_log_index'] ?? 0),
            lastLogTerm: (int) ($data['last_log_term'] ?? 0)
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
