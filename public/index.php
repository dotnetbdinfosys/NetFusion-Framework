<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : public/index.php
| Module    : Public
| Namespace : None (Global Entry Point)
| Purpose   : Front Controller and public HTTP request entry point for NetFusion Framework.
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
| File      : public/index.php
| Module    : Public
| Namespace : None (Global Entry Point)
| Purpose   : Front Controller and public HTTP request entry point for NetFusion Framework.
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
| File      : public/index.php
| Module    : Public
| Namespace : None (Global Entry Point)
| Purpose   : Front Controller and public HTTP request entry point for NetFusion Framework.
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
 * NetFusion Framework — Front Controller
 *
 * All HTTP requests are routed through this single entry point via
 * the web server's rewrite rules (public/.htaccess or nginx.conf).
 *
 * This file is intentionally minimal — it simply:
 *   1. Records the request start time.
 *   2. Bootstraps the application.
 *   3. Hands the request to the HTTP Kernel.
 *   4. Sends the response and terminates.
 *
 * NEVER put business logic, routing, or query code in this file.
 *
 * @package NetFusion
 * @version 1.0.0
 */

// -----------------------------------------------------------------------------
// 1. Record the high-resolution start timestamp (performance telemetry)
// -----------------------------------------------------------------------------
define('NETFUSION_START', microtime(true));

// -----------------------------------------------------------------------------
// 2. Enforce PHP version requirement
// -----------------------------------------------------------------------------
if (PHP_VERSION_ID < 80100) {
    http_response_code(503);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'error'   => 'Unsupported PHP Version',
        'message' => sprintf(
            'NetFusion requires PHP >= 8.1. Running: PHP %s.',
            PHP_VERSION
        ),
    ]);
    exit(1);
}

// -----------------------------------------------------------------------------
// 2.1 First-Run Detection: Redirect to Web Installer if not yet installed
// -----------------------------------------------------------------------------
$baseDir = dirname(__DIR__);
if (!file_exists($baseDir . '/storage/installed')) {
    $requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
    $requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');
    if (!str_starts_with($requestPath, '/install') && !preg_match('/\.(css|js|ico|png|jpg|jpeg|svg|woff2?)$/i', $requestPath)) {
        header('Location: /install');
        exit;
    }
}

// -----------------------------------------------------------------------------
// 3. Bootstrap the application (loads .env, DI container, core bindings)
// -----------------------------------------------------------------------------
/** @var \NetFusion\Core\Application $app */
$app = require_once __DIR__ . '/../core/bootstrap.php';

// -----------------------------------------------------------------------------
// 4. Capture the HTTP request from PHP superglobals
// -----------------------------------------------------------------------------
$request = \NetFusion\Core\Http\Request::capture();

// -----------------------------------------------------------------------------
// 5. Resolve the HTTP Kernel and handle the request
// -----------------------------------------------------------------------------
/** @var \NetFusion\Core\Http\Kernel $kernel */
$kernel = $app->make(\NetFusion\Core\Http\Kernel::class);

$response = $kernel->handle($request);

// -----------------------------------------------------------------------------
// 6. Send the response to the client
// -----------------------------------------------------------------------------
$response->send();

// -----------------------------------------------------------------------------
// 7. Terminate the lifecycle (cleanup, queue flushing, etc.)
// -----------------------------------------------------------------------------
$kernel->terminate($request, $response);
