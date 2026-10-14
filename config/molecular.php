<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Holographic Storage & DNA Molecular Archival Configuration
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

return [
    /*
    |--------------------------------------------------------------------------
    | DNA Oligonucleotide Synthesis Settings
    |--------------------------------------------------------------------------
    */
    'dna' => [
        'default_chunk_size' => env('DNA_CHUNK_SIZE', 16),
        'default_rs_parity' => env('DNA_RS_PARITY', 6),
        'forward_primer' => env('DNA_FORWARD_PRIMER', 'ACGTACGT'),
        'reverse_primer' => env('DNA_REVERSE_PRIMER', 'TGCATGCA'),
        'max_homopolymer_run' => 1, // Enforced by Goldman ternary rotational mapping
    ],

    /*
    |--------------------------------------------------------------------------
    | Volumetric Holographic Page Memory Settings
    |--------------------------------------------------------------------------
    */
    'holographic' => [
        'default_wavelength_nm' => 532.0, // Nd:YAG 532nm Green Laser
        'crystal_thickness_mm' => 5.0,
        'angular_selectivity_deg' => 0.05,
        'page_dimensions' => [
            'width' => 32,
            'height' => 32,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cold-Vault Storage Settings
    |--------------------------------------------------------------------------
    */
    'vault' => [
        'default_facility' => env('MOLECULAR_VAULT_FACILITY', 'Svalbard_Molecular_Vault'),
        'default_condition' => 'cryogenic',
    ],
];
