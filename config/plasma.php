<?php
/**
 * NetFusion Framework - Enterprise Plasma Physics & Fusion Reactor Configuration
 *
 * @package    NetFusion\Config
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Tokamak Machine Dimensions (ITER Benchmark)
    |--------------------------------------------------------------------------
    */
    'major_radius'   => (float) env('PLASMA_MAJOR_RADIUS', 6.2),
    'minor_radius'   => (float) env('PLASMA_MINOR_RADIUS', 2.0),
    'toroidal_b0'    => (float) env('PLASMA_TOROIDAL_B0', 5.3),
    'plasma_current' => (float) env('PLASMA_CURRENT', 15.0e6),

    /*
    |--------------------------------------------------------------------------
    | Default Thermonuclear Fuel Mixture
    |--------------------------------------------------------------------------
    | Supported: 'd_t', 'd_d_n', 'd_d_p', 'd_he3', 'p_b11'.
    */
    'default_fuel'   => env('PLASMA_DEFAULT_FUEL', 'd_t'),

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'discharges'  => 'plasma_discharges',
        'diagnostics' => 'plasma_diagnostics',
    ],
];
