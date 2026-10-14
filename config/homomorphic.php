<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/homomorphic.php
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
     | Homomorphic Encryption & SMPC Configuration
     |--------------------------------------------------------------------------
     */
    'default_scheme'    => 'bfv',
    'bfv' => [
        'degree'        => 16,
        'modulus'       => 65537,
        'plain_modulus' => 17,
    ],
    'ckks' => [
        'degree'        => 16,
        'modulus'       => 1048576,
        'scale'         => 1024.0,
    ],
    'smpc' => [
        'prime'         => 2147483647,
        'min_threshold' => 2,
    ],
];
