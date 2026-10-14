<?php
/**
 * NetFusion Framework - Enterprise Computational Fluid Dynamics (CFD / LBM) Configuration
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
    | Default Lattice Topology
    |--------------------------------------------------------------------------
    | Standard D2Q9 (2D 9-velocity) lattice discretization model.
    */
    'lattice_model' => env('LBM_LATTICE_MODEL', 'D2Q9'),

    /*
    |--------------------------------------------------------------------------
    | Default Grid Dimensions
    |--------------------------------------------------------------------------
    | Grid spatial resolution in discrete lattice units (Nx, Ny).
    */
    'grid_width'  => (int) env('LBM_GRID_WIDTH', 50),
    'grid_height' => (int) env('LBM_GRID_HEIGHT', 50),

    /*
    |--------------------------------------------------------------------------
    | Single Relaxation Time (Tau)
    |--------------------------------------------------------------------------
    | Relaxation parameter tau (must satisfy tau > 0.5 for physical stability).
    | Kinematic viscosity nu = (1/3) * (tau - 0.5).
    */
    'tau' => (float) env('LBM_TAU', 0.6),

    /*
    |--------------------------------------------------------------------------
    | Default Collision Formulation
    |--------------------------------------------------------------------------
    | Supported collision models: 'bgk', 'trt', 'mrt'.
    */
    'collision_model' => env('LBM_COLLISION_MODEL', 'bgk'),

    /*
    |--------------------------------------------------------------------------
    | Database Persistence Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'simulations' => 'lbm_simulations',
        'fields'      => 'lbm_field_snapshots',
    ],
];
