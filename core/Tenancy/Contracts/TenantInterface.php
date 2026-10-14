<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Contracts/TenantInterface.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Contracts
| Purpose   : Standard contract representing a Tenant in SaaS multi-tenancy.
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

interface TenantInterface
{
    public function getTenantKey(): string;

    public function getName(): string;

    public function getSubdomain(): ?string;

    public function getDomain(): ?string;

    public function getDatabaseName(): string;

    public function isActive(): bool;

    public function getMetadata(): array;
}