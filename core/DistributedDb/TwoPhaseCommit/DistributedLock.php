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

namespace NetFusion\Core\DistributedDb\TwoPhaseCommit;

use JsonSerializable;

/**
 * Distributed lease lock with monotonic fencing token to prevent split-brain zombies.
 */
class DistributedLock implements JsonSerializable
{
    public function __construct(
        public readonly string $resource,
        public readonly string $owner,
        public readonly int $fencingToken,
        public readonly float $expiresAt,
        public readonly int $ttlMs
    ) {
    }

    public function isExpired(): bool
    {
        return microtime(true) > $this->expiresAt;
    }

    /**
     * Verify whether an incoming action's token is valid under this lock's fencing sequence.
     */
    public function isValidForFencing(int $token): bool
    {
        return $token >= $this->fencingToken;
    }

    public function toArray(): array
    {
        return [
            'resource' => $this->resource,
            'owner' => $this->owner,
            'fencing_token' => $this->fencingToken,
            'expires_at' => $this->expiresAt,
            'ttl_ms' => $this->ttlMs,
            'is_expired' => $this->isExpired(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
