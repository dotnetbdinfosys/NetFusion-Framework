<?php
/**
 * NetFusion Framework - Enterprise Numerical Relativity Configuration
 *
 * Configuration for Spacetime Metric Tensors and Gravitational Waveform Simulation.
 *
 * @package    NetFusion\Config
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

return [
    'default_metric' => env('RELATIVITY_DEFAULT_METRIC', 'schwarzschild'), // minkowski, schwarzschild, kerr, flrw

    'default_detector' => env('RELATIVITY_DEFAULT_DETECTOR', 'H1'), // H1, L1, V1, LISA

    'binary_coalescence' => [
        'default_m1_solar'     => (float) env('RELATIVITY_DEFAULT_M1', 36.0),
        'default_m2_solar'     => (float) env('RELATIVITY_DEFAULT_M2', 29.0),
        'default_distance_mpc' => (float) env('RELATIVITY_DEFAULT_DISTANCE_MPC', 410.0),
        'sample_rate_hz'       => (float) env('RELATIVITY_SAMPLE_RATE_HZ', 2048.0),
    ],

    'thresholds' => [
        'detection_snr_threshold' => 8.0, // Standard 8-sigma matched filter threshold
    ],
];
