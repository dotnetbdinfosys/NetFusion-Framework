<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/privacy.php
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
     | Differential Privacy & Budget Accounting Settings
     |--------------------------------------------------------------------------
     */
    'default_epsilon'   => 1.0,
    'default_delta'     => 1e-5,
    'default_mechanism' => 'laplace',
    'enforce_budget'    => true,
    'clip_bounds'       => [
        'default_lower' => 0.0,
        'default_upper' => 1000.0,
    ],
    'federated'         => [
        'default_clipping_norm' => 1.0,
        'min_nodes'             => 3,
    ],
];
