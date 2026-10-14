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
 * AppendEntries RPC response from follower back to leader.
 */
class AppendEntriesResponse implements JsonSerializable
{
    public function __construct(
        public readonly int $term,
        public readonly bool $success,
        public readonly int $matchIndex,
        public readonly string $nodeId = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'term' => $this->term,
            'success' => $this->success,
            'match_index' => $this->matchIndex,
            'node_id' => $this->nodeId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            term: (int) ($data['term'] ?? 0),
            success: (bool) ($data['success'] ?? false),
            matchIndex: (int) ($data['match_index'] ?? 0),
            nodeId: (string) ($data['node_id'] ?? '')
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
