<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Tenancy/Traits/BelongsToTenant.php
| Module    : Tenancy
| Namespace : NetFusion\Core\Tenancy\Traits
| Purpose   : Active Record Trait for automatic tenant scoping and ID injection.
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

namespace NetFusion\Core\Tenancy\Traits;

trait BelongsToTenant
{
    /**
     * Boot the trait on model creation: auto-inject tenant_id if not present.
     */
    public function initializeBelongsToTenant(): void
    {
        if (function_exists('tenant_id')) {
            $tid = tenant_id();
            if ($tid !== null && !isset($this->attributes['tenant_id'])) {
                $this->attributes['tenant_id'] = $tid;
            }
        }
    }

    public function getTenantId(): ?string
    {
        return isset($this->attributes['tenant_id']) ? (string)$this->attributes['tenant_id'] : null;
    }

    public function setTenantId(string $tenantId): static
    {
        $this->attributes['tenant_id'] = $tenantId;
        return $this;
    }
}