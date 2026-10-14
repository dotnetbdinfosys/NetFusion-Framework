<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Contracts/TenantResolverInterface.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Contracts
| Purpose   : Interface for resolving tenants from incoming HTTP requests.
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

namespace NetFusion\Core\Tenancy\Contracts;

use NetFusion\Core\Http\Request;

interface TenantResolverInterface
{
    public function resolve(Request $request): ?string;
}