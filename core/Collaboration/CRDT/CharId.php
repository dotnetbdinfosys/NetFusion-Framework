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

namespace NetFusion\Core\Collaboration\CRDT;

/**
 * Class CharId
 *
 * Immutable unique identifier for an RGA character node based on Lamport timestamps.
 */
class CharId
{
    public function __construct(
        public readonly int $clock,
        public readonly string $peerId,
        public readonly int $seq = 0
    ) {
    }

    /**
     * Compare this CharId with another for deterministic ordering.
     * Higher clock wins. If clocks are equal, peerId lexicographical order breaks tie.
     *
     * @return int Negative if $this < $other, 0 if equal, positive if $this > $other
     */
    public function compareTo(CharId $other): int
    {
        if ($this->clock !== $other->clock) {
            return $this->clock <=> $other->clock;
        }

        $peerCmp = strcmp($this->peerId, $other->peerId);
        if ($peerCmp !== 0) {
            return $peerCmp;
        }

        return $this->seq <=> $other->seq;
    }

    public function toString(): string
    {
        return "{$this->clock}@{$this->peerId}:{$this->seq}";
    }

    public static function fromString(string $id): self
    {
        if (preg_match('/^(\d+)@([^:]+):(\d+)$/', $id, $m)) {
            return new self((int) $m[1], $m[2], (int) $m[3]);
        }
        return new self(0, 'root', 0);
    }

    public function toArray(): array
    {
        return [
            'clock' => $this->clock,
            'peerId' => $this->peerId,
            'seq' => $this->seq,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['clock'] ?? 0),
            (string) ($data['peerId'] ?? 'root'),
            (int) ($data['seq'] ?? 0)
        );
    }
}
