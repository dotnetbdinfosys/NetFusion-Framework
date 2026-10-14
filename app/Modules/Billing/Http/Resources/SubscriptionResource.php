<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Http/Resources/SubscriptionResource.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Http\Resources
| Purpose   : API Resource transformer for Subscription model — clean state representation.
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

namespace NetFusion\Modules\Billing\Http\Resources;

use NetFusion\Core\Http\ApiResource;

/**
 * Subscription API Resource Transformer
 *
 * Transforms a Subscription model into a clean, standardized API JSON structure.
 * Exposes state, dates, and plan relationship cleanly.
 *
 * @package NetFusion\Modules\Billing\Http\Resources
 * @version 1.0.0
 */
class SubscriptionResource extends ApiResource
{
    /**
     * {@inheritDoc}
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $sub = is_object($this->resource)
            ? (array) $this->resource
            : (array) $this->resource;

        return [
            'id'                  => (int)    ($sub['id']                   ?? 0),
            'tenant_id'           => (string) ($sub['tenant_id']            ?? ''),
            'plan_id'             => (int)    ($sub['plan_id']              ?? 0),
            'status'              => (string) ($sub['status']               ?? 'trialing'),
            'is_active'           => in_array($sub['status'] ?? '', ['active', 'trialing'], true),
            'is_trialing'         => ($sub['status'] ?? '') === 'trialing',
            'trial_ends_at'       => $sub['trial_ends_at']       ?? null,
            'current_period_end'  => $sub['current_period_end']  ?? null,
            'canceled_at'         => $sub['canceled_at']         ?? null,
            'created_at'          => $sub['created_at']          ?? null,
            'updated_at'          => $sub['updated_at']          ?? null,
        ];
    }
}
