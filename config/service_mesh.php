<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Service Mesh Configuration
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
    | Primary Trust Domain
    |--------------------------------------------------------------------------
    |
    | Defines the SPIFFE trust domain for this mesh deployment.
    | Format: e.g. 'cluster.local' or 'mesh.example.com'
    |
    */
    'trust_domain' => env('MESH_TRUST_DOMAIN', 'cluster.local'),

    /*
    |--------------------------------------------------------------------------
    | mTLS Security Posture
    |--------------------------------------------------------------------------
    |
    | 'STRICT'     : Reject any non-mTLS plaintext connection.
    | 'PERMISSIVE' : Accept both mTLS and plaintext (migration mode).
    |
    */
    'mtls_mode' => env('MESH_MTLS_MODE', 'STRICT'),

    /*
    |--------------------------------------------------------------------------
    | Default Certificate TTL
    |--------------------------------------------------------------------------
    |
    | Lifetime of newly issued X.509 SVID workload certificates in seconds.
    | Default is 24 hours (86400 seconds).
    |
    */
    'certificate_ttl' => (int) env('MESH_CERT_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Certificate Rotation Grace Period
    |--------------------------------------------------------------------------
    |
    | Certificates expiring within this threshold (seconds) are automatically rotated.
    |
    */
    'rotation_grace_seconds' => (int) env('MESH_ROTATION_GRACE', 3600),

    /*
    |--------------------------------------------------------------------------
    | Sidecar Settings
    |--------------------------------------------------------------------------
    */
    'sidecar' => [
        'inbound_port' => (int) env('MESH_SIDECAR_INBOUND_PORT', 15006),
        'outbound_port' => (int) env('MESH_SIDECAR_OUTBOUND_PORT', 15001),
        'simulate' => (bool) env('MESH_SIMULATE', true),
    ],

];
