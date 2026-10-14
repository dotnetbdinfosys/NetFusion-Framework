<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/Exceptions/LockTimeoutException.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock\Exceptions
| Purpose   : Exception thrown when blocking lock wait exceeds timeout.
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

namespace NetFusion\Core\Lock\Exceptions;

use RuntimeException;

class LockTimeoutException extends RuntimeException
{
    public static function timedOut(string $name, int $seconds): self
    {
        return new self("Timed out after {$seconds} seconds waiting to acquire lock for [{$name}].");
    }
}