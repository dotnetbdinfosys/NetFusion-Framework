<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Database/Seeders/BillingDatabaseSeeder.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Database\Seeders
| Purpose   : Seeds default SaaS subscription plan tiers into the database.
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

namespace NetFusion\Modules\Billing\Database\Seeders;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\Seeders\Seeder;
use NetFusion\Modules\Billing\Models\Plan;

/**
 * NetFusion Billing Database Seeder
 *
 * Populates starter SaaS pricing tiers.
 *
 * @package NetFusion\Modules\Billing\Database\Seeders
 * @version 1.0.0
 */
class BillingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Connection $db): void
    {
        $plans = [
            [
                'name'             => 'Starter Tier',
                'slug'             => 'starter',
                'price'            => 29.00,
                'currency'         => 'USD',
                'billing_interval' => 'monthly',
                'features'         => json_encode(['Up to 5 Users', '10GB Storage', 'Standard Support']),
                'is_active'        => 1,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ],
            [
                'name'             => 'Professional Tier',
                'slug'             => 'professional',
                'price'            => 79.00,
                'currency'         => 'USD',
                'billing_interval' => 'monthly',
                'features'         => json_encode(['Up to 25 Users', '50GB Storage', 'Priority Support', 'Custom Domain']),
                'is_active'        => 1,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ],
            [
                'name'             => 'Enterprise Tier',
                'slug'             => 'enterprise',
                'price'            => 299.00,
                'currency'         => 'USD',
                'billing_interval' => 'monthly',
                'features'         => json_encode(['Unlimited Users', '500GB Storage', 'Dedicated Account Manager', 'SLA Guarantee']),
                'is_active'        => 1,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($plans as $planData) {
            $existing = Plan::findBySlug($planData['slug']);
            if (!$existing) {
                Plan::create($planData);
            }
        }
    }
}
