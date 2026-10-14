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

namespace NetFusion\Core\IaC\State\Stores;

use NetFusion\Core\IaC\State\Contracts\StateStoreInterface;
use NetFusion\Core\IaC\State\StateFile;
use NetFusion\Core\IaC\State\StateLock;

/**
 * High-speed in-memory state store for tests, previews, and ephemeral pipelines.
 */
class MemoryStateStore implements StateStoreInterface
{
    private ?StateFile $state = null;
    private ?StateLock $activeLock = null;

    public function __construct(?StateFile $initialState = null)
    {
        $this->state = $initialState;
    }

    public function read(): ?StateFile
    {
        return $this->state;
    }

    public function write(StateFile $state): void
    {
        $state->incrementSerial();
        $this->state = $state;
    }

    public function lock(StateLock $lock): bool
    {
        if ($this->activeLock !== null) {
            return false;
        }

        $this->activeLock = $lock;
        return true;
    }

    public function unlock(string $lockId): bool
    {
        if ($this->activeLock !== null && $this->activeLock->lockId === $lockId) {
            $this->activeLock = null;
            return true;
        }

        return false;
    }

    public function getActiveLock(): ?StateLock
    {
        return $this->activeLock;
    }
}
