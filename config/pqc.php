<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
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
    | Default Post-Quantum Algorithms
    |--------------------------------------------------------------------------
    |
    | Supported KEM: 'ml-kem-512', 'ml-kem-768' (default), 'ml-kem-1024'
    | Supported DSA: 'ml-dsa-44' (default), 'ml-dsa-65', 'ml-dsa-87'
    |
    */
    'default_kem' => env('PQC_DEFAULT_KEM', 'ml-kem-768'),
    'default_dsa' => env('PQC_DEFAULT_DSA', 'ml-dsa-44'),

    /*
    |--------------------------------------------------------------------------
    | Hybrid Mode Configuration
    |--------------------------------------------------------------------------
    |
    | Combine classical elliptic curves with post-quantum lattice primitives.
    |
    */
    'hybrid' => [
        'enabled'       => true,
        'classical_alg' => 'X25519',
        'kdf'           => 'hkdf-sha256',
        'aead'          => 'aes-256-gcm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quantum-Resistant Envelope Vault
    |--------------------------------------------------------------------------
    |
    | Configuration for storing and rotating master keys.
    |
    */
    'vault' => [
        'default_key_id' => env('PQC_VAULT_KEY_ID', 'master-v1'),
        'auto_rotate'    => false,
        'storage'        => env('PQC_VAULT_STORAGE', 'memory'), // memory or database
    ],
];
