<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Spatial Computing & WebXR Configuration
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Physics Gravity Vector
    |--------------------------------------------------------------------------
    */
    'default_gravity' => [
        'x' => 0.0,
        'y' => -9.81,
        'z' => 0.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default WebXR Reference Space
    |--------------------------------------------------------------------------
    |
    | Supported: 'local-floor', 'local', 'viewer', 'unbounded'
    |
    */
    'default_reference_space' => env('XR_DEFAULT_REFERENCE_SPACE', 'local-floor'),

    /*
    |--------------------------------------------------------------------------
    | 3D Gaussian Splatting Defaults
    |--------------------------------------------------------------------------
    */
    'gaussian_splatting' => [
        'max_splats' => 100000,
        'culling_distance' => 500.0,
        'depth_sorting' => true,
    ],
];
