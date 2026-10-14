<?php
/**
 * NetFusion Framework - Enterprise Superconductivity & Quantum Magnetics Configuration
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
    | Default Benchmark Superconductor Material
    |--------------------------------------------------------------------------
    | Supported: 'lead', 'nbti', 'nb3sn', 'mgb2', 'ybco', 'bscco'.
    */
    'default_material' => env('SUPERCOND_DEFAULT_MATERIAL', 'ybco'),

    /*
    |--------------------------------------------------------------------------
    | Default Operating Temperature (Kelvin)
    |--------------------------------------------------------------------------
    */
    'default_temperature' => (float) env('SUPERCOND_DEFAULT_TEMP', 77.0),

    /*
    |--------------------------------------------------------------------------
    | Default Applied Magnetic Field (Tesla)
    |--------------------------------------------------------------------------
    */
    'default_field' => (float) env('SUPERCOND_DEFAULT_FIELD', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'materials' => 'supercond_materials',
        'junctions' => 'supercond_junctions',
    ],
];
