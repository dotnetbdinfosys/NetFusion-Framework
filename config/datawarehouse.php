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

return [
    /*
    |--------------------------------------------------------------------------
    | Data Warehouse & Columnar Storage Engine Settings
    |--------------------------------------------------------------------------
    */
    'default_chunk_capacity' => 10000,

    'compression' => [
        'default_numeric' => 'bit_packing',
        'default_string' => 'dictionary',
        'default_repeated' => 'rle',
    ],

    'cache' => [
        'materialized_cubes' => true,
        'ttl' => 3600,
    ],
];
