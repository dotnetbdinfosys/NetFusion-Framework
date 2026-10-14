<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/config.php
| Module    : Modules\Billing
| Namespace : None (Module Config)
| Purpose   : Configuration settings for the SaaS Multi-Tenant Billing Module.
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

return [
    'enabled'          => true,
    'currency'         => 'USD',
    'currency_symbol'  => '$',
    'trial_days'       => 14,
    'tax_rate_percent' => 5.0,
    'invoice_prefix'   => 'INV-',
    'plans' => [
        'starter'      => ['name' => 'Starter Tier', 'price' => 29.00],
        'professional' => ['name' => 'Professional Tier', 'price' => 79.00],
        'enterprise'   => ['name' => 'Enterprise Tier', 'price' => 299.00],
    ],
];