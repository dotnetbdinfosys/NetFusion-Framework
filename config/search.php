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
    | Default Search Driver & Index
    |--------------------------------------------------------------------------
    */
    'driver' => env('SEARCH_DRIVER', 'memory'),
    'default' => env('SEARCH_DRIVER', 'memory'),
    'default_index' => 'products',

    /*
    |--------------------------------------------------------------------------
    | Search Drivers Configuration
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'memory' => [
            'driver' => 'memory',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', ':memory:'),
        ],
        'mysql' => [
            'driver' => 'mysql',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Search Index Options
    |--------------------------------------------------------------------------
    */
    'default_index_options' => [
        'stemming' => true,
        'bm25_k1' => 1.2,
        'bm25_b' => 0.75,
        'synonyms' => [
            'laptop' => ['notebook', 'macbook', 'ultrabook', 'pc'],
            'phone' => ['mobile', 'smartphone', 'cellphone', 'iphone', 'android'],
            'tv' => ['television', 'screen', 'monitor', 'display'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Suggest & Spell Correction
    |--------------------------------------------------------------------------
    */
    'suggest' => [
        'enabled' => true,
        'max_suggestions' => 5,
        'max_spell_distance' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hybrid Scoring Weights
    |--------------------------------------------------------------------------
    */
    'hybrid' => [
        'default_weight' => 0.5, // 0.5 = 50% BM25 lexical + 50% Dense Vector semantic
        'rrf_constant' => 60,
    ],
];
