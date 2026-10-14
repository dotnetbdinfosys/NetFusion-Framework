<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : config/mail.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Email transports, SMTP server credentials, and default sender configuration.
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
    | Default Mailer Transport
    |--------------------------------------------------------------------------
    |
    | Supported: "smtp", "log", "array"
    |
    */
    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Available transports and connection details.
    |
    */
    'mailers' => [

        'smtp' => [
            'transport'  => 'smtp',
            'host'       => env('MAIL_HOST', '127.0.0.1'),
            'port'       => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username'   => env('MAIL_USERNAME'),
            'password'   => env('MAIL_PASSWORD'),
            'timeout'    => 30,
        ],

        'log' => [
            'transport' => 'log',
            'path'      => storage_path('logs/mail.log'),
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | Default sender identity applied to all outgoing emails unless overridden.
    |
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@dotnetbd.com'),
        'name'    => env('MAIL_FROM_NAME', 'NetFusion Framework'),
    ],

];
