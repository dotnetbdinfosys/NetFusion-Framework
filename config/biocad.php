<?php
/**
 * NetFusion Framework - Enterprise Synthetic Biology & BioCAD Configuration
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
    | Default Cas Endonuclease Variant
    |--------------------------------------------------------------------------
    | Supported: 'SpCas9', 'SaCas9', 'Cas12a_AsCpf1', 'Cas13a'.
    */
    'default_cas' => env('BIOCAD_DEFAULT_CAS', 'SpCas9'),

    /*
    |--------------------------------------------------------------------------
    | Default Expression Host Organism
    |--------------------------------------------------------------------------
    | Supported: 'e_coli', 's_cerevisiae', 'h_sapiens', 'cho_cells'.
    */
    'default_host' => env('BIOCAD_DEFAULT_HOST', 'e_coli'),

    /*
    |--------------------------------------------------------------------------
    | Minimum Acceptable CAI Score
    |--------------------------------------------------------------------------
    | Codon Adaptation Index threshold below which optimization is recommended.
    */
    'min_cai_threshold' => (float) env('BIOCAD_MIN_CAI', 0.80),

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'guides'   => 'biocad_crispr_guides',
        'circuits' => 'biocad_circuits',
    ],
];
