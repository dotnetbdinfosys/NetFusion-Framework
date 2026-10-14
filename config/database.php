<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : config/database.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Database connection configurations for MySQL, PostgreSQL, SQLite, and Multi-Tenancy.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : config/database.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Database connection configurations for MySQL, PostgreSQL, SQLite, and Multi-Tenancy.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : config/database.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Database connection configurations for MySQL, PostgreSQL, SQLite, and Multi-Tenancy.
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

/**
 * NetFusion Database Configuration
 *
 * Supports multiple named connections (mysql, pgsql, sqlite) and
 * provides a tenant connection template for multi-tenancy.
 * Access via: config('database.default'), config('database.connections.mysql')
 *
 * @return array<string, mixed>
 */
return [

    // -------------------------------------------------------------------------
    // Default Database Driver
    // -------------------------------------------------------------------------

    /**
     * The default database connection to use.
     * Must match one of the connection names in the 'connections' array below.
     */
    'default' => env('DB_DRIVER', 'mysql'),

    // -------------------------------------------------------------------------
    // Named Database Connections
    // -------------------------------------------------------------------------

    'connections' => [

        /**
         * MySQL / MariaDB Connection
         * Used for most web applications and is the default NetFusion driver.
         */
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST', '127.0.0.1'),
            'port'        => (int) env('DB_PORT', 3306),
            'database'    => env('DB_DATABASE', 'netfusion_db'),
            'username'    => env('DB_USERNAME', 'root'),
            'password'    => env('DB_PASSWORD', ''),
            'charset'     => env('DB_CHARSET', 'utf8mb4'),
            'collation'   => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'      => env('DB_PREFIX', ''),
            'strict'      => true,
            'engine'      => 'InnoDB',
            'options'     => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_PERSISTENT         => false,
            ],
        ],

        /**
         * PostgreSQL Connection
         */
        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => env('PGSQL_HOST', '127.0.0.1'),
            'port'     => (int) env('PGSQL_PORT', 5432),
            'database' => env('PGSQL_DATABASE', 'netfusion_db'),
            'username' => env('PGSQL_USERNAME', 'postgres'),
            'password' => env('PGSQL_PASSWORD', ''),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
            'sslmode'  => 'prefer',
            'options'  => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        ],

        /**
         * SQLite Connection (ideal for local dev and testing)
         */
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('SQLITE_DATABASE', dirname(__DIR__) . '/storage/database.sqlite'),
            'prefix'   => '',
            'options'  => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        ],

    ],

    // -------------------------------------------------------------------------
    // Multi-Tenancy Database Configuration
    // -------------------------------------------------------------------------

    /**
     * Multi-tenancy strategy.
     * Options:
     *   'database' — Each tenant gets an isolated database (most secure).
     *   'prefix'   — Shared DB, tables prefixed by tenant_id.
     *   'none'     — No tenancy isolation (single-tenant mode).
     */
    'tenancy_mode' => env('TENANCY_MODE', 'database'),

    /**
     * The naming prefix for per-tenant databases.
     * e.g., 'tenant_' will create databases named 'tenant_acme', 'tenant_globex'
     */
    'tenant_db_prefix' => env('TENANT_DB_PREFIX', 'tenant_'),

    /**
     * Tenant connection template.
     * The Database Manager uses this template to build per-tenant connections
     * dynamically. The 'database' key is replaced at runtime.
     */
    'tenant_connection_template' => [
        'driver'    => env('DB_DRIVER', 'mysql'),
        'host'      => env('DB_HOST', '127.0.0.1'),
        'port'      => (int) env('DB_PORT', 3306),
        'username'  => env('DB_USERNAME', 'root'),
        'password'  => env('DB_PASSWORD', ''),
        'charset'   => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix'    => '',
        'options'   => [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],

    // -------------------------------------------------------------------------
    // Migrations
    // -------------------------------------------------------------------------

    /**
     * The directory where migration files are stored.
     * Relative to the application base path.
     */
    'migrations_path' => 'database/migrations',

    /**
     * The name of the migrations tracking table.
     */
    'migrations_table' => 'netfusion_migrations',

    // -------------------------------------------------------------------------
    // Query Logging (Dev Only)
    // -------------------------------------------------------------------------

    /**
     * Enable query logging (stores all executed queries in memory).
     * Set to false in production for performance.
     */
    'query_log' => env('APP_DEBUG', false),

];
