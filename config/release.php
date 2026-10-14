<?php
/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : config/release.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

return [
    /*
    |--------------------------------------------------------------------------
    | NetFusion Framework Official GA Release Configuration
    |--------------------------------------------------------------------------
    */
    'version'       => '1.0.0',
    'codename'      => 'Sovereign Quantum & Physical Intelligence Fabric',
    'release_date'  => '2026-10-13',
    'status'        => 'GA',
    'total_phases'  => 120,
    'license'       => 'MIT',
    'author'        => 'Md. Atiquzzaman Ashish',
    'company'       => 'DotNetBD',

    /*
    |--------------------------------------------------------------------------
    | System Requirements & Zero-Dependency Invariants
    |--------------------------------------------------------------------------
    */
    'requirements'  => [
        'php_min_version'                => '8.2.0',
        'runtime_external_dependencies'  => 0,
        'standard_file_headers_enforced' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification & Golden Master Diagnostics
    |--------------------------------------------------------------------------
    */
    'diagnostics'   => [
        'enforce_zero_dependencies' => true,
        'enforce_header_standards'   => true,
        'expected_migrations_count'  => 99,
        'strict_mode'               => true,
    ],
];
