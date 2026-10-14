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
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */
    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'smart_router'),

    /*
    |--------------------------------------------------------------------------
    | PCI-DSS Tokenization Vault Key
    |--------------------------------------------------------------------------
    */
    'vault_key' => env('PAYMENT_VAULT_KEY', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'),

    /*
    |--------------------------------------------------------------------------
    | Smart Router Settings
    |--------------------------------------------------------------------------
    */
    'router' => [
        'failure_threshold' => 3,
        'cooloff_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateways Configuration
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'stripe' => [
            'key' => env('STRIPE_KEY', 'pk_test_sample'),
            'secret' => env('STRIPE_SECRET', 'sk_test_sample'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', 'whsec_sample'),
            'priority' => 10,
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID', 'client_id_sample'),
            'client_secret' => env('PAYPAL_SECRET', 'secret_sample'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'priority' => 20,
        ],
        'bkash' => [
            'app_key' => env('BKASH_APP_KEY', 'bkash_key_sample'),
            'app_secret' => env('BKASH_APP_SECRET', 'bkash_secret_sample'),
            'username' => env('BKASH_USERNAME', 'bkash_user'),
            'password' => env('BKASH_PASSWORD', 'bkash_pass'),
            'priority' => 5,
        ],
        'nagad' => [
            'merchant_id' => env('NAGAD_MERCHANT_ID', 'nagad_sample'),
            'public_key' => env('NAGAD_PUBLIC_KEY', 'nagad_pub'),
            'private_key' => env('NAGAD_PRIVATE_KEY', 'nagad_priv'),
            'priority' => 5,
        ],
        'sslcommerz' => [
            'store_id' => env('SSLCZ_STORE_ID', 'ssl_test'),
            'store_passwd' => env('SSLCZ_STORE_PASSWD', 'ssl_pass'),
            'is_sandbox' => true,
            'priority' => 15,
        ],
    ],
];
