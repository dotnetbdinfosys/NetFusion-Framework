<?php
/**
 * NetFusion Framework - Enterprise 6G Wireless RIS Configuration
 *
 * Configuration for Reconfigurable Intelligent Surfaces, 6G THz beamforming and metamaterials.
 *
 * @package    NetFusion\Config
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

return [
    'default_surface' => 'ris_terahertz_primary',

    'frequency_band' => env('RIS_DEFAULT_BAND', 'terahertz'), // sub_6ghz, mmwave, terahertz, extreme_thz

    'default_resolution' => env('RIS_DEFAULT_RESOLUTION', 'continuous'), // continuous, 1_bit, 2_bit, 3_bit

    'default_grid' => [
        'rows' => (int) env('RIS_GRID_ROWS', 8),
        'cols' => (int) env('RIS_GRID_COLS', 8),
    ],

    'default_algorithm' => env('RIS_DEFAULT_ALGORITHM', 'mrt'), // mrt, zero_forcing, holographic_pencil

    'transceiver' => [
        'default_tx_power_watts' => 1.0,
        'noise_floor_watts'      => 1e-12,
    ],
];
