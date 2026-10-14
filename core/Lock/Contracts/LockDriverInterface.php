<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/Contracts/LockDriverInterface.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock\Contracts
| Purpose   : Driver contract for lock persistence (Database, File, Array, Redis).
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

interface LockDriverInterface
{
    public function acquire(string $name, string $owner, int $seconds): bool;

    public function release(string $name, string $owner): bool;

    public function forceRelease(string $name): void;

    public function getCurrentOwner(string $name): ?string;

    public function isLocked(string $name): bool;

    public function all(): array;

    public function cleanExpired(): int;
}