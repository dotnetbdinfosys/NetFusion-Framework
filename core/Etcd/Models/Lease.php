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

namespace NetFusion\Core\Etcd\Models;

/**
 * Represents a distributed lease with TTL and attached keys.
 */
class Lease
{
    /** @var array<string, bool> */
    protected array $attachedKeys = [];
    protected int $expiresAt;

    public function __construct(
        public readonly int $id,
        public readonly int $ttl,
        public readonly int $grantedAt = 0,
        ?int $expiresAt = null,
        array $keys = []
    ) {
        $now = $grantedAt > 0 ? $grantedAt : time();
        $this->expiresAt = $expiresAt ?? ($now + $this->ttl);
        foreach ($keys as $key) {
            $this->attachedKeys[$key] = true;
        }
    }

    /**
     * Attach a key to this lease.
     */
    public function attachKey(string $key): void
    {
        $this->attachedKeys[$key] = true;
    }

    /**
     * Detach a key from this lease.
     */
    public function detachKey(string $key): void
    {
        unset($this->attachedKeys[$key]);
    }

    /**
     * Check if a key is attached to this lease.
     */
    public function hasKey(string $key): bool
    {
        return isset($this->attachedKeys[$key]);
    }

    /**
     * Get all attached keys.
     *
     * @return array<string>
     */
    public function getKeys(): array
    {
        return array_keys($this->attachedKeys);
    }

    /**
     * Refresh the lease TTL (Keep-Alive).
     */
    public function keepAlive(?int $customTtl = null): int
    {
        $ttl = $customTtl ?? $this->ttl;
        $this->expiresAt = time() + $ttl;
        return $this->expiresAt;
    }

    /**
     * Check if the lease has expired.
     */
    public function isExpired(?int $now = null): bool
    {
        $time = $now ?? time();
        return $time >= $this->expiresAt;
    }

    /**
     * Get remaining time-to-live in seconds.
     */
    public function remainingTtl(?int $now = null): int
    {
        $time = $now ?? time();
        $remaining = $this->expiresAt - $time;
        return max(0, $remaining);
    }

    /**
     * Get expires timestamp.
     */
    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    /**
     * Export lease to array.
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'ttl'          => $this->ttl,
            'grantedAt'    => $this->grantedAt,
            'expiresAt'    => $this->expiresAt,
            'remainingTtl' => $this->remainingTtl(),
            'keys'         => $this->getKeys(),
        ];
    }
}
