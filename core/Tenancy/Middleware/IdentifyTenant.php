<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Middleware/IdentifyTenant.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Middleware
| Purpose   : Middleware to identify tenant and initialize tenancy context.
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

namespace NetFusion\Core\Tenancy\Middleware;

use Closure;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Tenancy\Exceptions\TenantNotFoundException;
use NetFusion\Core\Tenancy\TenantManager;

class IdentifyTenant
{
    public function __construct(protected TenantManager $tenancy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenancy->identify($request);

        if ($tenant !== null) {
            $this->tenancy->setTenant($tenant);
        }

        return $next($request);
    }
}