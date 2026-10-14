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
 * RequestVote RPC response from peer node back to candidate.
 */
class RequestVoteResponse implements JsonSerializable
{
    public function __construct(
        public readonly int $term,
        public readonly bool $voteGranted,
        public readonly string $voterId = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'term' => $this->term,
            'vote_granted' => $this->voteGranted,
            'voter_id' => $this->voterId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            term: (int) ($data['term'] ?? 0),
            voteGranted: (bool) ($data['vote_granted'] ?? false),
            voterId: (string) ($data['voter_id'] ?? '')
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
