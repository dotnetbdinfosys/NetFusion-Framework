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
    | Relying Party (RP) Settings
    |--------------------------------------------------------------------------
    */
    'rp_name' => env('WEBAUTHN_RP_NAME', env('APP_NAME', 'NetFusion Enterprise')),
    'rp_id'   => env('WEBAUTHN_RP_ID', 'localhost'),
    'origin'  => env('WEBAUTHN_ORIGIN', env('APP_URL', 'http://localhost:8000')),

    /*
    |--------------------------------------------------------------------------
    | Timeouts and Expiration
    |--------------------------------------------------------------------------
    */
    'timeout'       => (int)env('WEBAUTHN_TIMEOUT', 60000),      // 60 seconds
    'challenge_ttl' => (int)env('WEBAUTHN_CHALLENGE_TTL', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Authenticator Policy
    |--------------------------------------------------------------------------
    */
    'attachment'        => env('WEBAUTHN_ATTACHMENT', 'platform'),      // 'platform' or 'cross-platform'
    'user_verification' => env('WEBAUTHN_USER_VERIFICATION', 'preferred'), // 'required', 'preferred', 'discouraged'
    'resident_key'      => env('WEBAUTHN_RESIDENT_KEY', 'preferred'),     // 'required', 'preferred', 'discouraged'
    'attestation'       => env('WEBAUTHN_ATTESTATION', 'none'),           // 'none', 'direct', 'indirect'

    /*
    |--------------------------------------------------------------------------
    | Credential Storage Driver
    |--------------------------------------------------------------------------
    | Supported: 'database', 'array'
    */
    'driver' => env('WEBAUTHN_STORE_DRIVER', 'database'),
    'table'  => 'netfusion_webauthn_credentials',
];
