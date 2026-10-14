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

namespace NetFusion\Core\Wasm\Runtime;

use NetFusion\Core\Wasm\Vm\TrapException;

/**
 * Fuel / Gas metering for WebAssembly execution.
 * Guarantees bounded computation time and prevents unbounded CPU exhaustion.
 */
class FuelMeter
{
    private int $remainingFuel;
    private int $initialFuel;
    private int $consumedFuel = 0;
    private bool $enabled;

    public function __construct(int $fuel = 1000000, bool $enabled = true)
    {
        $this->remainingFuel = max(0, $fuel);
        $this->initialFuel = $this->remainingFuel;
        $this->enabled = $enabled;
    }

    public function consume(int $amount = 1): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->remainingFuel < $amount) {
            $this->consumedFuel += $this->remainingFuel;
            $this->remainingFuel = 0;
            throw TrapException::fuelExhausted();
        }

        $this->remainingFuel -= $amount;
        $this->consumedFuel += $amount;
    }

    public function getRemainingFuel(): int
    {
        return $this->remainingFuel;
    }

    public function getConsumedFuel(): int
    {
        return $this->consumedFuel;
    }

    public function setFuel(int $fuel): void
    {
        $this->remainingFuel = max(0, $fuel);
        $this->initialFuel = $this->remainingFuel;
        $this->consumedFuel = 0;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
