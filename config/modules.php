<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : config/modules.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Dynamic HMVC module loader settings, discovery paths, and auto-discovery toggles.
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
| File      : config/modules.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Dynamic HMVC module loader settings, discovery paths, and auto-discovery toggles.
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
| File      : config/modules.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Dynamic HMVC module loader settings, discovery paths, and auto-discovery toggles.
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
 * NetFusion Module Loader Configuration
 *
 * Controls how the Dynamic Module Loader discovers, validates,
 * and boots HMVC modules from the app/Modules/ directory.
 *
 * @return array<string, mixed>
 */
return [

    // -------------------------------------------------------------------------
    // Module Discovery
    // -------------------------------------------------------------------------

    /**
     * The root directory that will be scanned for HMVC modules.
     * Each sub-directory is treated as a separate module.
     */
    'path' => base_path('app/Modules'),

    /**
     * The filename that every valid module must contain.
     * If this file is missing, the directory is NOT treated as a module.
     * This file contains module metadata (name, version, dependencies, etc.)
     */
    'manifest_file' => 'module.json',

    /**
     * The fully-qualified class name of the module's Service Provider.
     * The loader will attempt to resolve and register this class automatically.
     * Convention: NetFusion\Modules\{ModuleName}\Providers\ModuleServiceProvider
     */
    'provider_class_pattern' => 'NetFusion\\Modules\\{module}\\Providers\\ModuleServiceProvider',

    // -------------------------------------------------------------------------
    // Explicitly Enabled Modules
    // -------------------------------------------------------------------------

    /**
     * An explicit list of modules to load, in priority order.
     *
     * If this list is empty, the loader will discover ALL modules
     * found in the 'path' directory automatically.
     *
     * Use this to control load order when modules have dependencies.
     *
     * @var array<int, string>  Module directory names (e.g., 'SaaS', 'PMS')
     */
    'modules' => [
        // 'Core',       // Core module loaded first (dependencies for all others)
        // 'SaaS',       // SaaS & Multi-tenant engine
        // 'PMS',        // Property Management System
        // 'HMS',        // Hospital Management System
        // 'Accounting', // Financial Accounting Engine
        // 'School',     // School ERP
        // 'University', // University ERP
    ],

    // -------------------------------------------------------------------------
    // Explicitly Disabled Modules
    // -------------------------------------------------------------------------

    /**
     * Modules in this list will be SKIPPED during discovery and booting,
     * even if they have a valid module.json manifest.
     *
     * @var array<int, string>
     */
    'disabled' => [
        // 'ExperimentalModule',
    ],

    // -------------------------------------------------------------------------
    // Module Cache
    // -------------------------------------------------------------------------

    /**
     * Enable module discovery result caching.
     * In production, enable this for near-zero module discovery overhead.
     * The cache is stored in storage/cache/modules.cache.php.
     *
     * Clear with: php netfusion module:clear-cache
     */
    'cache_enabled' => env('APP_ENV', 'local') === 'production',

    /**
     * Path to the module discovery cache file.
     */
    'cache_file' => base_path('storage/cache/modules.cache.php'),

    // -------------------------------------------------------------------------
    // Module Auto-Discovery Features
    // -------------------------------------------------------------------------

    /**
     * Features that are automatically discovered and registered per module.
     * Disable any feature here to skip its discovery globally.
     */
    'auto_discover' => [
        'routes'     => true,   // {Module}/Routes/web.php & api.php
        'views'      => true,   // {Module}/Views/ → registered as view namespace
        'migrations' => true,   // {Module}/Database/Migrations/
        'seeders'    => true,   // {Module}/Database/Seeders/
        'events'     => true,   // {Module}/Events/ & {Module}/Listeners/
        'commands'   => true,   // {Module}/Commands/
        'config'     => true,   // {Module}/config.php merged into Config
        'policies'   => true,   // {Module}/Policies/
        'observers'  => true,   // {Module}/Observers/
    ],

];
