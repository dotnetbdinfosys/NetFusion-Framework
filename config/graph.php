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
    | Default Graph Engine Driver & Name
    |--------------------------------------------------------------------------
    */
    'driver' => env('GRAPH_DRIVER', 'memory'), // 'memory' or 'database'
    'default' => 'default',

    /*
    |--------------------------------------------------------------------------
    | PageRank Algorithm Defaults
    |--------------------------------------------------------------------------
    */
    'pagerank' => [
        'damping_factor' => 0.85,
        'max_iterations' => 100,
        'tolerance' => 1e-6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Community Detection (LPA) Defaults
    |--------------------------------------------------------------------------
    */
    'community' => [
        'max_iterations' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Semantic Triples & Reasoner Defaults
    |--------------------------------------------------------------------------
    */
    'reasoner' => [
        'max_inference_rounds' => 20,
    ],
];
