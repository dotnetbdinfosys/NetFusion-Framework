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
    | Default Workflow State Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "database", "array"
    |
    */
    'default' => env('WORKFLOW_DRIVER', 'database'),

    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'default'),
            'table_prefix' => 'nf_workflow_',
        ],

        'array' => [
            'driver' => 'array',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Engine Execution Settings
    |--------------------------------------------------------------------------
    */
    'max_iterations' => 1000,
    'saga_compensation_on_failure' => true,
    'activity_logging' => true,
];