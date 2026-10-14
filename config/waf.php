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
    | WAF Master Switch
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool)env('WAF_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Store Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "array", "database"
    |
    */
    'store' => env('WAF_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Anomaly Score Thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'warning' => (float)env('WAF_THRESHOLD_WARNING', 3.0),
        'blocking' => (float)env('WAF_THRESHOLD_BLOCKING', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Challenge & HMAC Secret
    |--------------------------------------------------------------------------
    */
    'secret' => env('WAF_SECRET', env('APP_KEY', 'default-netfusion-waf-secret-32-chars!')),

    /*
    |--------------------------------------------------------------------------
    | Layer 7 DDoS Adaptive Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'enabled' => true,
        'max_requests' => (int)env('WAF_RATE_MAX_REQUESTS', 120),
        'window_seconds' => (int)env('WAF_RATE_WINDOW_SECONDS', 60),
        'ban_threshold_ratio' => (int)env('WAF_RATE_BAN_RATIO', 3),
        'ban_duration' => (int)env('WAF_RATE_BAN_DURATION', 900), // 15 mins
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Ban Attacking IPs
    |--------------------------------------------------------------------------
    */
    'auto_ban' => [
        'enabled' => (bool)env('WAF_AUTOBAN_ENABLED', true),
        'ttl' => (int)env('WAF_AUTOBAN_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Static Whitelist & Blacklist
    |--------------------------------------------------------------------------
    */
    'ip_whitelist' => [
        '127.0.0.1',
        '::1',
    ],

    'ip_blacklist' => [
    ],
];
