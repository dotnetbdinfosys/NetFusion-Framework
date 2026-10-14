<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/selfhealing.php
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
    | Autonomous Self-Healing Infrastructure Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('SELF_HEALING_ENABLED', true),

    'anomaly_detection' => [
        'window_size'   => (int)env('SH_WINDOW_SIZE', 50),
        'z_threshold'   => (float)env('SH_Z_THRESHOLD', 3.0),
        'min_samples'   => (int)env('SH_MIN_SAMPLES', 5),
    ],

    'blast_radius' => [
        'max_disrupted_percent'  => (float)env('SH_MAX_DISRUPT_PCT', 25.0),
        'max_concurrent_actions' => (int)env('SH_MAX_CONCURRENT', 2),
        'cooldown_seconds'       => (int)env('SH_COOLDOWN_SEC', 60),
    ],

    'canary' => [
        'steps' => [10.0, 50.0, 100.0],
    ],

    'default_fleet_size' => (int)env('SH_FLEET_SIZE', 10),
];
