<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : config/notifications.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Multi-channel notification delivery settings and providers.
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

    /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    |
    | The default channels that notifications will use if not specified.
    | Supported: "database", "mail", "webhook", "sms", "broadcast"
    |
    */
    'default' => ['database'],

    /*
    |--------------------------------------------------------------------------
    | SMS Notification Channel Settings
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "log", "array", "http"
    |
    */
    'sms' => [
        'driver'  => env('SMS_DRIVER', 'array'),
        'from'    => env('SMS_FROM', 'NetFusion'),
        'gateway' => [
            'url'    => env('SMS_GATEWAY_URL', ''),
            'key'    => env('SMS_GATEWAY_KEY', ''),
            'secret' => env('SMS_GATEWAY_SECRET', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Notification Channel Settings
    |--------------------------------------------------------------------------
    |
    | Outgoing webhook settings for Slack, Discord, or generic endpoints.
    |
    */
    'webhook' => [
        'timeout' => 5,
        'secret'  => env('NOTIFICATION_WEBHOOK_SECRET', ''),
    ],
];
