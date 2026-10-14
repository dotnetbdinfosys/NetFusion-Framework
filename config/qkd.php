<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/qkd.php
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
    | Quantum Key Distribution (QKD) & Entanglement Mesh Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('QKD_ENABLED', true),

    'default_protocol' => env('QKD_DEFAULT_PROTOCOL', 'bb84'),

    'qber_threshold' => (float)env('QKD_QBER_THRESHOLD', 0.11), // 11.0%

    'default_photons' => (int)env('QKD_DEFAULT_PHOTONS', 256),

    'sample_fraction' => (float)env('QKD_SAMPLE_FRACTION', 0.20),

    'key_size_bytes' => (int)env('QKD_KEY_SIZE_BYTES', 32), // 256-bit symmetric keys
];
