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

use JsonSerializable;
use RuntimeException;

/**
 * Class FencingToken
 *
 * Monotonically increasing epoch token used to fence off former primaries
 * and prevent split-brain write anomalies.
 */
class FencingToken implements JsonSerializable
{
    public function __construct(
        public readonly int $epoch,
        public readonly string $nodeId,
        public readonly float $issuedAt = 0.0
    ) {
    }

    public function getEpoch(): int
    {
        return $this->epoch;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    /**
     * Check if this token is stale compared to the current cluster epoch.
     */
    public function isStale(int $clusterEpoch): bool
    {
        return $this->epoch < $clusterEpoch;
    }

    /**
     * Assert token is valid; throws exception if fenced/stale.
     */
    public function assertValid(int $clusterEpoch): void
    {
        if ($this->isStale($clusterEpoch)) {
            throw new RuntimeException(
                "Fencing violation: Node '{$this->nodeId}' has stale epoch {$this->epoch}, current cluster epoch is {$clusterEpoch}"
            );
        }
    }

    public function next(string $nodeId): self
    {
        return new self($this->epoch + 1, $nodeId, microtime(true));
    }

    public function toArray(): array
    {
        return [
            'epoch'     => $this->epoch,
            'node_id'   => $this->nodeId,
            'issued_at' => $this->issuedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
