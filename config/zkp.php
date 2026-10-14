<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Elliptic Curve & Finite Field Order
    |--------------------------------------------------------------------------
    |
    | Supported: "bn254" (Alt-bn128), "baby_jubjub", "secp256k1"
    |
    */
    'default_field' => env('ZKP_FIELD', 'bn254'),

    /*
    |--------------------------------------------------------------------------
    | Default Proof System
    |--------------------------------------------------------------------------
    |
    | Supported: "groth16", "schnorr", "bulletproofs"
    |
    */
    'default_system' => env('ZKP_SYSTEM', 'groth16'),

    /*
    |--------------------------------------------------------------------------
    | Security Level (Bits)
    |--------------------------------------------------------------------------
    */
    'security_level' => 128,

    /*
    |--------------------------------------------------------------------------
    | Key Storage Directory
    |--------------------------------------------------------------------------
    */
    'storage_path' => storage_path('zkp/keys'),
];
