<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Exceptions/TenantNotActiveException.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Exceptions
| Purpose   : Exception thrown when accessing a suspended or inactive tenant.
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

class TenantNotActiveException extends RuntimeException
{
    public static function forTenant(string $tenantKey): self
    {
        return new self("Tenant account [{$tenantKey}] is inactive or suspended.");
    }
}