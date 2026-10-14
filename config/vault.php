<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Vault Storage Driver
    |--------------------------------------------------------------------------
    | Options: 'database', 'array'
    */
    'driver' => env('VAULT_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Master Key (KEK) Configuration
    |--------------------------------------------------------------------------
    | Base64-encoded or raw 32-byte secret key string.
    */
    'master_keys' => [
        1 => env('VAULT_MASTER_KEY', env('APP_KEY', 'default-netfusion-master-key-32-chars!')),
    ],

    'active_key_version' => (int)env('VAULT_KEY_VERSION', 1),

    /*
    |--------------------------------------------------------------------------
    | Dynamic Leases Default Settings
    |--------------------------------------------------------------------------
    | Default TTL (in seconds) for dynamic generated credentials.
    */
    'lease' => [
        'default_ttl' => (int)env('VAULT_DEFAULT_LEASE_TTL', 3600),
        'max_ttl' => (int)env('VAULT_MAX_LEASE_TTL', 86400),
    ],
];
