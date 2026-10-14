<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : config/broadcasting.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Real-time broadcast and Server-Sent Events (SSE) configuration.
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
    | Default Broadcast Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcasted.
    |
    | Supported: "sse", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'sse'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to emit events to other systems or web clients.
    |
    */

    'connections' => [

        'sse' => [
            'driver'     => 'sse',
            'storage'    => storage_path('framework/broadcasting/sse'),
            'heartbeat'  => 15, // Heartbeat interval in seconds
            'reconnect'  => 3000, // Reconnect delay for client in ms
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
