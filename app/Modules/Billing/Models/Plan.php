<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Models/Plan.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Models
| Purpose   : Active Record Model for SaaS subscription plans and pricing tiers.
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

namespace NetFusion\Modules\Billing\Models;

use NetFusion\Core\Database\Model;

/**
 * NetFusion Plan Model
 *
 * Represents a SaaS tier plan (Starter, Professional, Enterprise).
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property float  $price
 * @property string $currency
 * @property string $billing_interval
 * @property string $features
 * @property int    $is_active
 *
 * @package NetFusion\Modules\Billing\Models
 * @version 1.0.0
 */
class Plan extends Model
{
    protected string $table = 'billing_plans';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'billing_interval',
        'features',
        'is_active',
    ];

    /**
     * Find a plan by its unique slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        $data = static::query()->where('slug', $slug)->first();
        return $data ? static::newFromBuilder($data) : null;
    }

    /**
     * Return all currently active plans.
     *
     * @return static[]
     */
    public static function getActive(): array
    {
        $rows = static::query()->where('is_active', 1)->orderBy('price', 'asc')->get();
        return array_map([static::class, 'newFromBuilder'], $rows);
    }

    /**
     * Get features as an array.
     *
     * @return string[]
     */
    public function getFeaturesList(): array
    {
        $raw = $this->features;
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : explode(',', (string) $raw);
    }
}
