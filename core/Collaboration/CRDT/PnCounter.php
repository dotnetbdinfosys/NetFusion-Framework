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
 * Class PnCounter
 *
 * Positive-Negative Counter CRDT supporting concurrent increments and decrements.
 */
class PnCounter
{
    /** @var array<string, int> */
    private array $p = [];

    /** @var array<string, int> */
    private array $n = [];

    public function __construct(
        public readonly string $peerId = 'peer-1'
    ) {
    }

    public function increment(int $amount = 1): void
    {
        if ($amount > 0) {
            $this->p[$this->peerId] = ($this->p[$this->peerId] ?? 0) + $amount;
        }
    }

    public function decrement(int $amount = 1): void
    {
        if ($amount > 0) {
            $this->n[$this->peerId] = ($this->n[$this->peerId] ?? 0) + $amount;
        }
    }

    public function value(): int
    {
        $sumP = array_sum($this->p);
        $sumN = array_sum($this->n);
        return $sumP - $sumN;
    }

    public function merge(PnCounter $other): void
    {
        foreach ($other->p as $peer => $val) {
            $this->p[$peer] = max($this->p[$peer] ?? 0, $val);
        }
        foreach ($other->n as $peer => $val) {
            $this->n[$peer] = max($this->n[$peer] ?? 0, $val);
        }
    }

    public function toArray(): array
    {
        return [
            'peerId' => $this->peerId,
            'p' => $this->p,
            'n' => $this->n,
            'value' => $this->value(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $instance = new self((string) ($data['peerId'] ?? 'peer-1'));
        $instance->p = (array) ($data['p'] ?? []);
        $instance->n = (array) ($data['n'] ?? []);
        return $instance;
    }
}
