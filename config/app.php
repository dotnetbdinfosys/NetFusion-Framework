<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : config/app.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Application core configuration: name, environment, timezone, security keys.
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
| File      : config/app.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Application core configuration: name, environment, timezone, security keys.
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
| File      : config/app.php
| Module    : Configuration
| Namespace : None (Config Array)
| Purpose   : Application core configuration: name, environment, timezone, security keys.
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
 * NetFusion Application Configuration
 *
 * Exposes core application settings to the Config Manager.
 * Access via: config('app.name'), config('app.debug'), etc.
 *
 * @return array<string, mixed>
 */
return [

    // -------------------------------------------------------------------------
    // Application Identity
    // -------------------------------------------------------------------------

    /**
     * The display name of the application.
     * Shown in emails, error pages, and the CLI tool header.
     */
    'name' => env('APP_NAME', 'NetFusion Framework'),

    /**
     * The current application version.
     */
    'version' => env('APP_VERSION', '1.5.3'),

    /**
     * The application environment.
     * Options: local | staging | testing | production
     */
    'env' => env('APP_ENV', 'production'),

    /**
     * Enable/disable debug mode.
     * NEVER set to true in production environments.
     */
    'debug' => env('APP_DEBUG', false),

    /**
     * The application URL (used for generating absolute URLs, asset URLs, etc.)
     */
    'url' => env('APP_URL', 'http://localhost'),

    /**
     * The application asset URL.
     */
    'asset_url' => env('ASSET_URL', env('APP_URL', 'http://localhost')),

    // -------------------------------------------------------------------------
    // Localization
    // -------------------------------------------------------------------------

    /**
     * Default application timezone.
     * Must be a valid PHP timezone identifier.
     * @see https://www.php.net/manual/en/timezones.php
     */
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),

    /**
     * Default application locale.
     */
    'locale' => env('APP_LOCALE', 'en'),

    /**
     * Fallback locale when a translation is not found.
     */
    'fallback_locale' => 'en',

    // -------------------------------------------------------------------------
    // Security
    // -------------------------------------------------------------------------

    /**
     * Application encryption key.
     * This value should be a random 32-character string.
     * Generate with: php netfusion key:generate
     */
    'key' => env('APP_KEY', ''),

    /**
     * The cipher algorithm used for encryption.
     */
    'cipher' => 'AES-256-CBC',

    // -------------------------------------------------------------------------
    // Core Service Providers
    // -------------------------------------------------------------------------

    /**
     * The list of Service Providers that will be registered and booted
     * with the application. These are the core framework providers.
     * Module providers are discovered and registered dynamically.
     *
     * @var array<int, class-string>
     */
    'providers' => [
        // Core Framework Providers (Phase 1 - skeleton only)
        // Routing, Database, Session, and Event providers will be added in Phase 2+
    ],

    // -------------------------------------------------------------------------
    // Core Aliases
    // -------------------------------------------------------------------------

    /**
     * Class aliases registered in the container.
     * These are available via app('alias') or the app() helper.
     *
     * @var array<string, class-string>
     */
    'aliases' => [
        'app'    => \NetFusion\Core\Application::class,
        'config' => \NetFusion\Core\Config\Config::class,
    ],

];
