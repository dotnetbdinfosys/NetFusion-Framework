<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Resolvers/HeaderTenantResolver.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Resolvers
| Purpose   : Resolves tenant identifier from X-Tenant-ID HTTP headers.
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

class HeaderTenantResolver implements TenantResolverInterface
{
    public function __construct(protected string $header = 'X-Tenant-ID')
    {
    }

    public function resolve(Request $request): ?string
    {
        $id = $request->header($this->header) ?? $request->header('X-Tenant');
        return (is_string($id) && $id !== '') ? $id : null;
    }
}