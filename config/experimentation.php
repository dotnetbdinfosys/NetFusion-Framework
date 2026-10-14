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
    | Default Experimentation Storage Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "database", "array"
    |
    */
    'default' => env('EXPERIMENTATION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Auto Track Exposure
    |--------------------------------------------------------------------------
    |
    | When set to true, calls to experiment()->evaluate() or ab_test()
    | automatically record exposure in the storage store.
    |
    */
    'auto_track_exposure' => env('EXPERIMENTATION_AUTO_EXPOSURE', true),

    /*
    |--------------------------------------------------------------------------
    | Default Statistical Alpha
    |--------------------------------------------------------------------------
    |
    | Significance level for hypothesis testing (0.05 = 95% confidence).
    |
    */
    'statistical_alpha' => 0.05,
];
