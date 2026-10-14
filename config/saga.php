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

/*
|--------------------------------------------------------------------------
| NetFusion Framework Configuration
|--------------------------------------------------------------------------
|
| File      : config/saga.php
| Module    : Saga Orchestrator & Distributed Transactions
| Purpose   : Configuration for Saga orchestration state storage and retries.
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
    | Default Saga State Storage Driver
    |--------------------------------------------------------------------------
    | Supported: "array", "database"
    */
    'default_store' => env('SAGA_STORE', 'array'),

    /*
    |--------------------------------------------------------------------------
    | Saga Store Driver Configurations
    |--------------------------------------------------------------------------
    */
    'stores' => [

        'array' => [],

        'database' => [
            'instances_table' => 'netfusion_saga_instances',
            'steps_table'     => 'netfusion_saga_steps',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global Execution Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'step_timeout' => (float) env('SAGA_DEFAULT_TIMEOUT', 30.0),
        'step_retries' => (int) env('SAGA_DEFAULT_RETRIES', 0),
        'retry_delay_ms' => (float) env('SAGA_RETRY_DELAY_MS', 100.0),
    ],

];
