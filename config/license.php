<?php
/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/license.php
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
    /*
    |--------------------------------------------------------------------------
    | DotNetBD License CMS API Endpoint Settings (Engine v5.1.8)
    |--------------------------------------------------------------------------
    */
    'server_url'    => env('LICENSE_SERVER_URL', 'https://lic.dotnetbd.com'),
    'product_id'    => (int) env('LICENSE_PRODUCT_ID', 1),
    'company_name'  => env('LICENSE_COMPANY_NAME', 'DotNetBD Client'),
    'license_key'   => env('LICENSE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Server Hash Salt & Security
    |--------------------------------------------------------------------------
    | Salt string used in computing md5(domain . salt) for client server verification.
    */
    'salt'          => env('LICENSE_SALT', 'DotNetBD_NetFusion_Secure_Salt_2026'),

    /*
    |--------------------------------------------------------------------------
    | Local Cache & Storage Configuration
    |--------------------------------------------------------------------------
    | Cache remote validation for 24 hours (86400 seconds) to ensure optimal
    | latency and network fault resilience.
    */
    'cache_ttl'     => 86400, // 24 Hours in seconds
    'storage_path'  => dirname(__DIR__) . '/storage/framework/license.lic',

    /*
    |--------------------------------------------------------------------------
    | Enforcement & Grace Period Policy
    |--------------------------------------------------------------------------
    | When enabled, protected web routes enforce active license validity.
    */
    'enforce'       => env('LICENSE_ENFORCE', false),
    'grace_period'  => 86400 * 3, // 3 days grace period if remote server is unreachable
];
