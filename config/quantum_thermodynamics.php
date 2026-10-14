<?php
/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/quantum_thermodynamics.php
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
    | Fundamental Physical Constants
    |--------------------------------------------------------------------------
    */
    'hbar'       => 1.054571817e-34,   // Reduced Planck constant (J·s)
    'boltzmann'  => 1.380649e-23,      // Boltzmann constant k_B (J/K)
    'electron_volt' => 1.602176634e-19, // 1 eV in Joules

    /*
    |--------------------------------------------------------------------------
    | Default Quantum Otto Engine Parameters
    |--------------------------------------------------------------------------
    */
    'engine' => [
        'omega_cold_hz' => 1.0e9,  // Cold stroke angular frequency (rad/s) - microwave qubit
        'omega_hot_hz'  => 2.0e9,  // Hot stroke angular frequency (rad/s)
        'temp_cold_k'   => 100.0,  // Cold bath temperature (Kelvin)
        'temp_hot_k'    => 300.0,  // Hot bath temperature (Kelvin)
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Lindblad Master Equation Integration Settings
    |--------------------------------------------------------------------------
    */
    'lindblad' => [
        'default_dt_sec'    => 1e-9,     // Default RK4 timestep (1 ns)
        'default_steps'     => 100,      // Default number of integration steps
        'steady_state_tol'  => 1e-12,    // Convergence tolerance for steady-state
        'max_steps'         => 100000,   // Maximum steps for steady-state iteration
    ],

    /*
    |--------------------------------------------------------------------------
    | Landauer Erasure Settings
    |--------------------------------------------------------------------------
    */
    'landauer' => [
        'reference_temp_k'  => 300.0,   // Standard room temperature reference
    ],
];
