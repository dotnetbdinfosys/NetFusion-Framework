<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | State Store Backend Configuration
    |--------------------------------------------------------------------------
    |
    | Supported backends: 'memory', 'file', 'database'
    |
    */
    'state' => [
        'backend' => 'file',
        'path' => 'storage/iac/terraform.tfstate',
        'lock_timeout_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Provider Settings
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'aws' => [
            'region' => 'us-east-1',
            'profile' => 'default',
        ],
        'google' => [
            'project' => 'netfusion-core',
            'zone' => 'us-central1-a',
        ],
        'local' => [
            'socket' => '/var/run/docker.sock',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Engine Parameters
    |--------------------------------------------------------------------------
    */
    'execution' => [
        'parallelism' => 10,
        'auto_rollback_on_failure' => true,
    ],
];
