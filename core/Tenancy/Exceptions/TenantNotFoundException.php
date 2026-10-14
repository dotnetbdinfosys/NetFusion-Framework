<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Exceptions/TenantNotFoundException.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Exceptions
| Purpose   : Exception thrown when requested tenant cannot be found.
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

namespace NetFusion\Core\Tenancy\Exceptions;

use RuntimeException;

class TenantNotFoundException extends RuntimeException
{
    public static function forIdentifier(string $identifier): self
    {
        return new self("Tenant with identifier [{$identifier}] could not be resolved.");
    }
}