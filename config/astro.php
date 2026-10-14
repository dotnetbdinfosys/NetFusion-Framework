<?php
/**
 * NetFusion Framework - Enterprise Astro-Informatics & Celestial Mechanics Configuration
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
    | Default Symplectic Integrator Type
    |--------------------------------------------------------------------------
    | Supported: 'leapfrog', 'yoshida_4th', 'rk4', 'euler'.
    */
    'default_integrator' => env('ASTRO_DEFAULT_INTEGRATOR', 'leapfrog'),

    /*
    |--------------------------------------------------------------------------
    | Plummer Softening Length (Meters)
    |--------------------------------------------------------------------------
    | Prevents unphysical close-encounter gravitational singularities.
    */
    'softening_length' => (float) env('ASTRO_SOFTENING_LENGTH', 1000.0),

    /*
    |--------------------------------------------------------------------------
    | Default Central Gravitational Parameter (mu = G * M)
    |--------------------------------------------------------------------------
    | Standard solar mu = 1.32712440018e20 m^3/s^2.
    */
    'default_mu' => (float) env('ASTRO_DEFAULT_MU', 1.32712440018e20),

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'bodies'    => 'astro_celestial_bodies',
        'ephemeris' => 'astro_ephemeris_records',
    ],
];
