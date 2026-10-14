<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : config/socket.php
| Module    : Sockets
| Purpose   : Configuration for Real-Time WebSockets and IPC Push server.
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
    | Server Host & Port
    |--------------------------------------------------------------------------
    |
    | The host interface and TCP port for incoming WebSocket client connections.
    |
    */
    'host' => env('SOCKET_HOST', '0.0.0.0'),
    'port' => (int) env('SOCKET_PORT', 8080),

    /*
    |--------------------------------------------------------------------------
    | IPC Broadcast Push Port
    |--------------------------------------------------------------------------
    |
    | Local TCP port for CLI jobs, queues, and HTTP processes to push broadcast
    | events directly to the running WebSocket server daemon.
    |
    */
    'push_host' => env('SOCKET_PUSH_HOST', '127.0.0.1'),
    'push_port' => (int) env('SOCKET_PUSH_PORT', 8081),

    /*
    |--------------------------------------------------------------------------
    | Max Clients & Limits
    |--------------------------------------------------------------------------
    |
    | Maximum concurrent WebSocket client connections allowed on a single node.
    |
    */
    'max_clients' => (int) env('SOCKET_MAX_CLIENTS', 10000),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat & Idle Timers
    |--------------------------------------------------------------------------
    |
    | Ping interval and connection idle timeout in seconds.
    |
    */
    'ping_interval' => (int) env('SOCKET_PING_INTERVAL', 30),
    'idle_timeout'  => (int) env('SOCKET_IDLE_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Allowed CORS/WebSocket origins (* for all).
    |
    */
    'allowed_origins' => ['*'],

];
