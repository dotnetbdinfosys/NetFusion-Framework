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

namespace NetFusion\Core\IaC\State\Contracts;

use NetFusion\Core\IaC\State\StateFile;
use NetFusion\Core\IaC\State\StateLock;

/**
 * Contract for IaC state file persistence and cryptographic lock backends.
 */
interface StateStoreInterface
{
    /**
     * Read the latest StateFile from storage.
     */
    public function read(): ?StateFile;

    /**
     * Write updated StateFile to storage.
     */
    public function write(StateFile $state): void;

    /**
     * Acquire an exclusive lock on the state store.
     */
    public function lock(StateLock $lock): bool;

    /**
     * Release an acquired state lock.
     */
    public function unlock(string $lockId): bool;

    /**
     * Retrieve active lock, if any.
     */
    public function getActiveLock(): ?StateLock;
}
