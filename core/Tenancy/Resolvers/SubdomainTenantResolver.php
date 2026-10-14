<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Resolvers/SubdomainTenantResolver.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Resolvers
| Purpose   : Resolves tenant identifier from request subdomain.
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

namespace NetFusion\Core\Tenancy\Resolvers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Tenancy\Contracts\TenantResolverInterface;

class SubdomainTenantResolver implements TenantResolverInterface
{
    /**
     * @var array<string>
     */
    protected array $excludedSubdomains = ['www', 'api', 'admin', 'app', 'localhost', 'staging', 'mail'];

    public function resolve(Request $request): ?string
    {
        $subdomain = $request->subdomain();
        if ($subdomain && !in_array($subdomain, $this->excludedSubdomains, true)) {
            return $subdomain;
        }

        return null;
    }
}