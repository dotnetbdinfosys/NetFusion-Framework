<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : config/filesystems.php
| Module    : Config
| Namespace : None (Config Array)
| Purpose   : Configuration for filesystem disks (Local, Public, S3) and storage symlinks.
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
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The default filesystem disk that should be used by the framework.
    |
    */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Supported Drivers: "local", "s3"
    |
    */
    'disks' => [

        'local' => [
            'driver'      => 'local',
            'root'        => storage_path('app'),
            'permissions' => 0755,
        ],

        'public' => [
            'driver'      => 'local',
            'root'        => storage_path('app/public'),
            'url'         => env('APP_URL', 'http://localhost') . '/storage',
            'permissions' => 0755,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID', ''),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY', ''),
            'region'                  => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket'                  => env('AWS_BUCKET', ''),
            'url'                     => env('AWS_URL', ''),
            'endpoint'                => env('AWS_ENDPOINT', ''),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | The symbolic links that should be created when the `storage:link` CLI
    | command is executed.
    |
    */
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
