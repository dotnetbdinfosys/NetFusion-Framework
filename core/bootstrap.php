<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/bootstrap.php
| Module    : Core
| Namespace : None (Bootstrap)
| Purpose   : Framework bootstrapper: loads autoloader, initializes storage, and instantiates Application.
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
| File      : core/bootstrap.php
| Module    : Core
| Namespace : None (Bootstrap)
| Purpose   : Framework bootstrapper: loads autoloader, initializes storage, and instantiates Application.
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
| File      : core/bootstrap.php
| Module    : Core
| Namespace : None (Bootstrap)
| Purpose   : Framework bootstrapper: loads autoloader, initializes storage, and instantiates Application.
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

use NetFusion\Core\Application;

/**
 * NetFusion Bootstrap File
 *
 * This file is the primary bootstrap entry point for the framework.
 * It is required by public/index.php (web) and netfusion (CLI tool).
 *
 * Responsibilities:
 *   1. Load the Composer PSR-4 autoloader.
 *   2. Instantiate the Application (which loads .env and configures runtime).
 *   3. Return the Application instance for use by the caller.
 *
 * @package NetFusion\Core
 * @version 1.0.0
 */

// -----------------------------------------------------------------------------
// 1. Determine the application base path (two levels above this file)
// -----------------------------------------------------------------------------
$basePath = dirname(__DIR__);

// -----------------------------------------------------------------------------
// 2. Load the Composer PSR-4 autoloader
// -----------------------------------------------------------------------------
$autoloaderPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!file_exists($autoloaderPath)) {
    http_response_code(503);
    echo json_encode([
        'error'   => 'Service Unavailable',
        'message' => 'Composer autoloader not found. Run: composer install',
    ]);
    exit(1);
}

require_once $autoloaderPath;

// -----------------------------------------------------------------------------
// 3. Ensure storage directories exist with correct permissions
// -----------------------------------------------------------------------------
$storageDirs = [
    $basePath . '/storage',
    $basePath . '/storage/logs',
    $basePath . '/storage/cache',
    $basePath . '/storage/sessions',
    $basePath . '/storage/views',
    $basePath . '/storage/migrations',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, recursive: true);
    }
}

// -----------------------------------------------------------------------------
// 4. Instantiate the Application
//    - Parses .env, configures PHP runtime, registers core bindings.
// -----------------------------------------------------------------------------
$app = new Application($basePath);

// -----------------------------------------------------------------------------
// 5. Return the Application to the caller (web or CLI)
// -----------------------------------------------------------------------------
return $app;
