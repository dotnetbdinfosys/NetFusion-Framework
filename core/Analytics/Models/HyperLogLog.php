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

namespace NetFusion\Core\Analytics\Models;

/**
 * Probabilistic cardinality estimator using HyperLogLog algorithm.
 * Provides approximate distinct counting (COUNT DISTINCT) with bounded relative error ~1.04 / sqrt(m).
 */
class HyperLogLog
{
    protected int $p; // precision bits (e.g. 10 -> m = 1024 registers)
    protected int $m; // number of registers = 2^p
    /** @var array<int, int> */
    protected array $registers;

    public function __construct(int $precision = 10)
    {
        $this->p = max(4, min(16, $precision));
        $this->m = 1 << $this->p;
        $this->registers = array_fill(0, $this->m, 0);
    }

    /**
     * Add an item (e.g. user_id, ip, url) into HLL sketch.
     */
    public function add(string $item): self
    {
        // 64-bit MurmurHash3 / FNV-1a approximation via md5
        $hashHex = md5($item);
        $hashPart = hexdec(substr($hashHex, 0, 8)); // 32-bit uint

        $j = $hashPart & ($this->m - 1); // register index
        $w = $hashPart >> $this->p;      // remaining bits

        // Count leading zeros + 1
        $rho = 1;
        while ($w > 0 && ($w & 1) === 0 && $rho < 32) {
            $rho++;
            $w >>= 1;
        }

        if ($rho > $this->registers[$j]) {
            $this->registers[$j] = $rho;
        }

        return $this;
    }

    /**
     * Merge another HLL instance into this one.
     */
    public function merge(self $other): self
    {
        for ($i = 0; $i < $this->m; $i++) {
            $this->registers[$i] = max($this->registers[$i], $other->registers[$i] ?? 0);
        }
        return $this;
    }

    /**
     * Estimate the cardinality (distinct count).
     */
    public function cardinality(): int
    {
        // Alpha constant
        $alpha = match ($this->m) {
            16 => 0.673,
            32 => 0.697,
            64 => 0.709,
            default => 0.7213 / (1.0 + 1.079 / $this->m),
        };

        $sum = 0.0;
        $zeros = 0;
        for ($i = 0; $i < $this->m; $i++) {
            $sum += 1.0 / (1 << $this->registers[$i]);
            if ($this->registers[$i] === 0) {
                $zeros++;
            }
        }

        $estimate = $alpha * $this->m * $this->m / $sum;

        // Small range correction (Linear Counting)
        if ($estimate <= 2.5 * $this->m && $zeros > 0) {
            $estimate = $this->m * log((float)$this->m / $zeros);
        }

        return (int)round($estimate);
    }

    /**
     * Export raw register state for serialization.
     *
     * @return array<int, int>
     */
    public function getRegisters(): array
    {
        return $this->registers;
    }

    /**
     * Import raw registers from serialized state.
     *
     * @param array<int, int> $registers
     */
    public function setRegisters(array $registers): self
    {
        $this->registers = $registers;
        return $this;
    }
}
