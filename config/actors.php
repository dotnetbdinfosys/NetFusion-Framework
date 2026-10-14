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
    | Actor System Name
    |--------------------------------------------------------------------------
    */
    'name' => env('ACTOR_SYSTEM_NAME', 'netfusion-system'),

    /*
    |--------------------------------------------------------------------------
    | Default State Store Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "database", "array"
    |
    */
    'driver' => env('ACTOR_STORE_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Default Mailbox Type
    |--------------------------------------------------------------------------
    |
    | Supported: "unbounded", "priority"
    |
    */
    'default_mailbox' => 'unbounded',
];
