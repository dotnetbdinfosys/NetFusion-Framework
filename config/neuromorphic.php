<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/neuromorphic.php
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
     | Neuromorphic Computing Configuration
     |--------------------------------------------------------------------------
     */
    'default_model' => env('NEUROMORPHIC_MODEL', 'lif'), // lif, izhikevich, adaptive_lif

    'lif' => [
        'tau_membrane'      => (float) env('LIF_TAU_M', 20.0),      // ms
        'v_rest'            => (float) env('LIF_V_REST', -70.0),     // mV
        'v_reset'           => (float) env('LIF_V_RESET', -75.0),    // mV
        'v_threshold'       => (float) env('LIF_V_TH', -55.0),       // mV
        'refractory_period' => (float) env('LIF_T_REF', 2.0),        // ms
        'resistance'        => (float) env('LIF_R_M', 10.0),         // MOhm
    ],

    'izhikevich' => [
        'a'      => (float) env('IZH_A', 0.02),
        'b'      => (float) env('IZH_B', 0.2),
        'c'      => (float) env('IZH_C', -65.0),
        'd'      => (float) env('IZH_D', 8.0),
        'v_peak' => (float) env('IZH_V_PEAK', 30.0),
    ],

    'stdp' => [
        'enabled'   => (bool) env('STDP_ENABLED', true),
        'a_plus'    => (float) env('STDP_A_PLUS', 0.01),
        'a_minus'   => (float) env('STDP_A_MINUS', 0.012),
        'tau_plus'  => (float) env('STDP_TAU_PLUS', 20.0),
        'tau_minus' => (float) env('STDP_TAU_MINUS', 20.0),
        'w_min'     => 0.0,
        'w_max'     => 1.0,
    ],

    'mesh' => [
        'max_queue_capacity' => (int) env('NEURO_MESH_MAX_QUEUE', 100000),
        'default_timesteps'  => (int) env('NEURO_TIMESTEPS', 30),
        'default_dt'         => 1.0, // ms
    ],
];
