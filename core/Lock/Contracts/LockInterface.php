<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/Contracts/LockInterface.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock\Contracts
| Purpose   : Interface for high-concurrency atomic distributed locks.
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

namespace NetFusion\Core\Lock\Contracts;

interface LockInterface
{
    public function acquire(): bool;

    public function release(): bool;

    public function forceRelease(): void;

    public function getOwner(): string;

    public function getName(): string;

    public function isAcquired(): bool;

    public function isAcquiredByCurrentProcess(): bool;

    /**
     * Attempts to acquire the lock. If acquired, executes callback and releases automatically.
     */
    public function get(?callable $callback = null): mixed;

    /**
     * Blocks execution until lock is acquired or timeout expires.
     */
    public function block(int $waitTimeoutSeconds, ?callable $callback = null): mixed;
}