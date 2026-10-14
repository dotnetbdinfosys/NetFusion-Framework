<?php
/**
 * NetFusion Framework - Enterprise Topological Data Analysis Configuration
 *
 * Configuration for Vietoris-Rips complexes, filtration limits, and distance metrics.
 *
 * @package    NetFusion\Config
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

return [
    'default_metric' => env('TDA_DEFAULT_METRIC', 'euclidean'), // euclidean, manhattan, cosine

    'max_dimension' => (int) env('TDA_MAX_DIMENSION', 2),

    'filtration' => [
        'default_max_radius' => (float) env('TDA_MAX_FILTRATION', 2.0),
        'curve_steps'        => (int) env('TDA_CURVE_STEPS', 20),
    ],

    'visualization' => [
        'plot_mode' => env('TDA_PLOT_MODE', 'barcode'), // barcode, scatter_persistence, summary
    ],
];
