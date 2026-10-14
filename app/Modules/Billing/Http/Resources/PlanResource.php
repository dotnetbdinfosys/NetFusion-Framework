<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Http/Resources/PlanResource.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Http\Resources
| Purpose   : API Resource transformer for the Plan model — controls public JSON representation.
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
 * Plan API Resource Transformer
 *
 * Transforms a Plan model (array representation) into a clean, standardized
 * API JSON structure. Controls exactly which fields are exposed and how
 * they are formatted (price formatting, feature parsing, etc.).
 *
 * @package NetFusion\Modules\Billing\Http\Resources
 * @version 1.0.0
 */
class PlanResource extends ApiResource
{
    /**
     * {@inheritDoc}
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $plan = is_object($this->resource)
            ? (array) $this->resource
            : (array) $this->resource;

        // Parse features: stored as JSON string in DB, expose as array
        $features = [];
        if (!empty($plan['features'])) {
            $decoded = json_decode($plan['features'], true);
            $features = is_array($decoded) ? $decoded : explode(',', $plan['features']);
        }

        return [
            'id'               => (int)    ($plan['id']               ?? 0),
            'name'             => (string) ($plan['name']             ?? ''),
            'slug'             => (string) ($plan['slug']             ?? ''),
            'price'            => (float)  ($plan['price']            ?? 0.00),
            'price_formatted'  => '$' . number_format((float) ($plan['price'] ?? 0), 2),
            'currency'         => (string) ($plan['currency']         ?? 'USD'),
            'billing_interval' => (string) ($plan['billing_interval'] ?? 'monthly'),
            'features'         => $features,
            'is_active'        => (bool)   ($plan['is_active']        ?? true),
            'created_at'       => $plan['created_at'] ?? null,
        ];
    }
}
