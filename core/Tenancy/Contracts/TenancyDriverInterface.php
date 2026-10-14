<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Contracts/TenancyDriverInterface.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Contracts
| Purpose   : Contract for data isolation strategies (Database, Column, Schema).
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

interface TenancyDriverInterface
{
    public function initialize(TenantInterface $tenant): void;

    public function end(): void;

    public function getMode(): string;
}