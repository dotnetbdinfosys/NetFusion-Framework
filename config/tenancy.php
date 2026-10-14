<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework - Multi-Tenancy Architecture Configuration
|--------------------------------------------------------------------------
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Tenancy Isolation Mode
    |--------------------------------------------------------------------------
    | Supported:
    |   - "database" : Separate SQLite / MySQL database per tenant.
    |   - "column"   : Shared database using tenant_id column scoping.
    |   - "schema"   : PostgreSQL schema / namespace isolation.
    */
    'mode' => env('TENANCY_MODE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Database Isolation Settings
    |--------------------------------------------------------------------------
    */
    'database' => [
        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        'default_connection' => 'sqlite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution Chain
    |--------------------------------------------------------------------------
    | Default resolution strategies enabled in incoming HTTP request pipeline.
    */
    'identification' => [
        'header'    => true,
        'subdomain' => true,
        'path'      => true,
        'domain'    => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Central Domain / System Subdomains Excluded from Tenant Routing
    |--------------------------------------------------------------------------
    */
    'central_domains' => [
        'localhost',
        '127.0.0.1',
        'app.localhost',
    ],

    'excluded_subdomains' => [
        'www',
        'api',
        'admin',
        'app',
        'localhost',
        'staging',
        'mail',
    ],
];