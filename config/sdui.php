<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Server-Driven UI (SDUI) Configuration
    |--------------------------------------------------------------------------
    */

    'schema_version' => '1.0.0',

    'app_secret' => env('SDUI_SECRET', 'sdui-default-hmac-secret-key-32ch'),

    'cache' => [
        'enabled' => true,
        'ttl'     => 3600, // 1 hour
    ],

    'theme' => [
        'is_dark_mode' => false,
        'colors' => [
            'primary'    => '#0066CC',
            'secondary'  => '#6C757D',
            'background' => '#F8F9FA',
            'surface'    => '#FFFFFF',
            'text'       => '#212529',
            'error'      => '#DC3545',
            'warning'    => '#FFC107',
            'success'    => '#28A745',
        ],
    ],

    'hydration' => [
        'enable_checksum'   => true,
        'enable_signatures' => true,
        'stream_transport'  => 'sse', // 'sse' or 'websocket'
    ],
];
