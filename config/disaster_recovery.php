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
    | Primary Region Configuration
    |--------------------------------------------------------------------------
    */
    'primary_region' => env('DR_PRIMARY_REGION', 'us-east-1'),

    /*
    |--------------------------------------------------------------------------
    | Regional DR Nodes
    |--------------------------------------------------------------------------
    */
    'nodes' => [
        'node-us-east-1' => [
            'region'   => 'us-east-1',
            'role'     => 'PRIMARY',
            'endpoint' => env('DR_ENDPOINT_PRIMARY', 'https://us-east.netfusion.io'),
        ],
        'node-eu-west-1' => [
            'region'   => 'eu-west-1',
            'role'     => 'STANDBY',
            'endpoint' => env('DR_ENDPOINT_STANDBY_EU', 'https://eu-west.netfusion.io'),
        ],
        'node-ap-southeast-1' => [
            'region'   => 'ap-southeast-1',
            'role'     => 'WITNESS',
            'endpoint' => env('DR_ENDPOINT_WITNESS_AP', 'https://ap-southeast.netfusion.io'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Continuous Data Protection (CDP) Settings
    |--------------------------------------------------------------------------
    */
    'cdp' => [
        'enabled'              => env('DR_CDP_ENABLED', true),
        'journal_storage'      => env('DR_JOURNAL_STORAGE', 'memory'), // memory, sqlite, file
        'snapshot_interval_m' => 60,
        'hash_chaining'        => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA Targets (Objectives)
    |--------------------------------------------------------------------------
    */
    'sla' => [
        'target_rto_seconds' => env('DR_TARGET_RTO_SEC', 30.0), // Recovery Time Objective
        'target_rpo_seconds' => env('DR_TARGET_RPO_SEC', 5.0),  // Recovery Point Objective
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Cloud DNS Routing
    |--------------------------------------------------------------------------
    */
    'dns' => [
        'provider' => env('DR_DNS_PROVIDER', 'simulated'), // simulated, cloudflare, route53
        'ttl'      => env('DR_DNS_TTL', 30),
    ],
];
