<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Brain-Computer Interface (BCI) Configuration
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Sampling Frequency (Hz)
    |--------------------------------------------------------------------------
    | Standard clinical EEG operates at 250 Hz, 500 Hz, or 1000 Hz.
    */
    'sampling_rate' => 250.0,

    /*
    |--------------------------------------------------------------------------
    | Power-Line Notch Filter (Hz)
    |--------------------------------------------------------------------------
    | 50.0 Hz (Europe / Asia) or 60.0 Hz (Americas) power grid hum removal.
    */
    'notch_frequency' => 50.0,

    /*
    |--------------------------------------------------------------------------
    | Electrode Contact Impedance Safety Threshold (kOhm)
    |--------------------------------------------------------------------------
    | Impedances above 10 kOhm introduce thermal & capacitive drift artifacts.
    */
    'max_electrode_impedance_kohms' => 10.0,

    /*
    |--------------------------------------------------------------------------
    | Default Montage Channels
    |--------------------------------------------------------------------------
    */
    'channels' => ['Fp1', 'Fp2', 'F3', 'F4', 'C3', 'C4', 'Cz', 'P3', 'P4', 'Pz', 'O1', 'O2'],

    /*
    |--------------------------------------------------------------------------
    | Neuro-Feedback Thresholds
    |--------------------------------------------------------------------------
    */
    'neurofeedback' => [
        'relaxation_threshold' => 1.5, // Alpha / Theta ratio
        'focus_threshold'      => 1.2, // Beta / (Theta + Alpha) ratio
        'hysteresis'           => 0.1,
    ],
];
