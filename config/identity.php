<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Decentralized Identity & Verifiable Credentials Configuration
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default DID Method
    |--------------------------------------------------------------------------
    |
    | Supported methods: 'netfusion', 'key', 'ion', 'web'
    |
    */
    'default_method' => env('DID_DEFAULT_METHOD', 'netfusion'),

    /*
    |--------------------------------------------------------------------------
    | Default Cryptographic Suite
    |--------------------------------------------------------------------------
    |
    | Supported: 'Ed25519Signature2020', 'PostQuantumMlDsa44Signature2026', 'BbsBlsSignature2020'
    |
    */
    'default_signature_suite' => env('IDENTITY_SIGNATURE_SUITE', 'Ed25519Signature2020'),

    /*
    |--------------------------------------------------------------------------
    | StatusList2021 Capacity & Bitstring
    |--------------------------------------------------------------------------
    |
    | Default number of credential slots per revocation bitstring list
    |
    */
    'status_list_capacity' => env('IDENTITY_STATUS_LIST_CAPACITY', 131072),

    /*
    |--------------------------------------------------------------------------
    | Trust Registry Ecosystem Settings
    |--------------------------------------------------------------------------
    */
    'trust_registry' => [
        'default_ecosystem' => 'netfusion-ecosystem-global',
        'cache_ttl_seconds' => 3600,
    ],
];
