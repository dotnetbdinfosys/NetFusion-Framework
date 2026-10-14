<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Resolvers/DomainTenantResolver.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Resolvers
| Purpose   : Resolves tenant identifier from custom fully qualified domain name.
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

class DomainTenantResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return $request->host();
    }
}