<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Optical Photonic Computing Configuration
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Telecom Laser Wavelength
    |--------------------------------------------------------------------------
    | Standard Silicon Photonics waveguide wavelength (1550nm C-Band).
    */
    'default_wavelength' => '1550nm',

    /*
    |--------------------------------------------------------------------------
    | Default MZI Mesh Topology
    |--------------------------------------------------------------------------
    | 'clements' (Rectangular, low optical depth) or 'reck' (Triangular).
    */
    'default_topology' => 'clements',

    /*
    |--------------------------------------------------------------------------
    | Physical Optical Parameters
    |--------------------------------------------------------------------------
    | Waveguide insertion loss per MZI cell in dB, and phase error tolerance.
    */
    'insertion_loss_per_cell_db' => 0.05,
    'phase_resolution_bits'      => 12, // 12-bit DAC thermo-optic control
    'laser_line_width_khz'       => 100.0,
];
