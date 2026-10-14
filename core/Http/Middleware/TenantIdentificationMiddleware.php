<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/TenantIdentificationMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Detects active tenant from request and dynamically configures tenant database connection.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/TenantIdentificationMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Detects active tenant from request and dynamically configures tenant database connection.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/TenantIdentificationMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Detects active tenant from request and dynamically configures tenant database connection.
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

namespace NetFusion\Core\Http\Middleware;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Tenancy\TenantManager;

/**
 * NetFusion Tenant Identification Middleware
 *
 * Automatically inspects the incoming Request, detects the Tenant,
 * switches the database connection to the tenant database, and binds the
 * Tenant instance to the Request attributes.
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class TenantIdentificationMiddleware implements MiddlewareInterface
{
    /**
     * @param TenantManager $tenants
     */
    public function __construct(
        private readonly TenantManager $tenants
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, callable $next): Response
    {
        $tenant = $this->tenants->identify($request);

        if ($tenant !== null) {
            $this->tenants->setTenant($tenant);
            $request->setAttribute('tenant', $tenant);
        }

        return $next($request);
    }
}
