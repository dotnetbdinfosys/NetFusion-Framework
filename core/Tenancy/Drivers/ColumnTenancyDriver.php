<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Drivers/ColumnTenancyDriver.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Drivers
| Purpose   : Shared database with column-based (tenant_id) isolation driver.
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

namespace NetFusion\Core\Tenancy\Drivers;

use NetFusion\Core\Tenancy\Contracts\TenancyDriverInterface;
use NetFusion\Core\Tenancy\Contracts\TenantInterface;

class ColumnTenancyDriver implements TenancyDriverInterface
{
    protected ?string $activeTenantId = null;

    public function initialize(TenantInterface $tenant): void
    {
        $this->activeTenantId = $tenant->getTenantKey();
    }

    public function end(): void
    {
        $this->activeTenantId = null;
    }

    public function getActiveTenantId(): ?string
    {
        return $this->activeTenantId;
    }

    public function getMode(): string
    {
        return 'column';
    }
}