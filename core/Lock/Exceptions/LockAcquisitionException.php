<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Lock/Exceptions/LockAcquisitionException.php
| Module    : Lock
| Namespace : NetFusion\Core\Lock\Exceptions
| Purpose   : Exception thrown when lock acquisition fails.
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

class LockAcquisitionException extends RuntimeException
{
    public static function failed(string $name): self
    {
        return new self("Could not acquire lock for resource [{$name}].");
    }
}