<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/swarm.php
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
     | Autonomous Swarm Intelligence Configuration
     |--------------------------------------------------------------------------
     */
    'default_colony' => env('SWARM_DEFAULT_COLONY', 'colony_alpha'),

    'grid' => [
        'width'              => (int) env('SWARM_GRID_WIDTH', 25),
        'height'             => (int) env('SWARM_GRID_HEIGHT', 25),
        'max_concentration'  => (float) env('SWARM_MAX_CONCENTRATION', 100.0),
        'base_evaporation'   => (float) env('SWARM_EVAPORATION_RATE', 0.05),
        'base_diffusion'     => (float) env('SWARM_DIFFUSION_RATE', 0.1),
    ],

    'bft' => [
        'cluster_secret'     => env('SWARM_BFT_SECRET', 'bft-swarm-secret-key-2026'),
        'quorum_ratio'       => 0.67, // > 2/3 Byzantine Fault Tolerance bound
    ],

    'auction' => [
        'bid_timeout_ms'     => (float) env('SWARM_BID_TIMEOUT_MS', 3000.0),
        'max_concurrency'    => (int) env('SWARM_MAX_CONCURRENCY', 100),
    ],
];
