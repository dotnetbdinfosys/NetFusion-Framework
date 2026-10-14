<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Resolvers/PathTenantResolver.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Resolvers
| Purpose   : Resolves tenant identifier from URL path prefix (e.g. /t/{tenant}/...).
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

class PathTenantResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);

        if (count($segments) >= 2 && ($segments[0] === 't' || $segments[0] === 'tenant')) {
            return $segments[1] !== '' ? $segments[1] : null;
        }

        return null;
    }
}