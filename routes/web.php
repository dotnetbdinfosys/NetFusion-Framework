<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : routes/web.php
| Module    : Routing
| Namespace : None (Global Web Routes)
| Purpose   : Global web routes for browser interactions with middleware support.
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
| File      : routes/web.php
| Module    : Routing
| Namespace : None (Global Web Routes)
| Purpose   : Global web routes for browser interactions with middleware support.
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
| File      : routes/web.php
| Module    : Routing
| Namespace : None (Global Web Routes)
| Purpose   : Global web routes for browser interactions with middleware support.
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

use NetFusion\Core\Routing\Router;
use NetFusion\Core\Http\Response;

/**
 * NetFusion Web Routes
 *
 * Define browser-facing routes here. These routes are automatically
 * wrapped in the 'web' middleware group (Session, CSRF, etc.).
 *
 * Route files receive a $router variable (the Router instance).
 *
 * @var Router $router
 */

// ============================================================
//  Framework Welcome Route
// ============================================================

$router->get('/', function (\NetFusion\Core\Http\Request $request) {
    if ($request->wantsJson() || $request->query('format') === 'json') {
        return Response::json([
            'framework'   => 'NetFusion Framework',
            'version'     => app()->version(),
            'status'      => 'operational',
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'phase'       => 'Phase 120 — Golden Master Certified ✓',
            'diagnostics' => [
                'boot_time_ms'   => app()->elapsedTime(),
                'peak_memory_mb' => app()->peakMemory(),
                'app_name'       => config('app.name'),
                'timezone'       => config('app.timezone'),
            ],
            'endpoints' => [
                'GET  /'                => 'Dashboard & Framework Portal',
                'GET  /admin/dashboard' => 'Master Executive Dashboard',
                'GET  /license'         => 'DotNetBD License CMS Portal',
                'GET  /health'          => 'Framework health check',
                'GET  /about'           => 'About NetFusion',
                'GET  /api/status'      => 'API status (via api.php)',
            ],
        ]);
    }

    return app(\NetFusion\App\Http\Controllers\DashboardController::class)->index($request);
})->name('home');

$router->get('/admin', function () {
    return Response::redirect('/admin/dashboard');
});

// ============================================================
//  Health Check
// ============================================================

$router->get('/health', [\NetFusion\App\Http\Controllers\HealthController::class, 'index'])->name('health');

// ============================================================
//  About NetFusion
// ============================================================

$router->get('/about', function () {
    return Response::html(
        '<h1>NetFusion Framework</h1>'
        . '<p>A modern, high-performance, enterprise-grade HMVC framework for PHP 8.x.</p>'
        . '<p>Version: ' . e(app()->version()) . '</p>'
    );
})->name('about');

// ============================================================
//  Demo: Route Parameters
// ============================================================

$router->get('/users/{id}', function (\NetFusion\Core\Http\Request $request) {
    return Response::json([
        'message' => 'User profile',
        'user_id' => $request->route('id'),
        'query'   => $request->query(),
    ]);
})->whereNumber('id')->name('users.show');

// ============================================================
//  Interactive Web Setup Wizard (Phase 23)
// ============================================================

$router->get('/install', [\NetFusion\App\Http\Controllers\InstallController::class, 'index'])->name('install.index');
$router->post('/install/check-db', [\NetFusion\App\Http\Controllers\InstallController::class, 'checkDatabase'])->name('install.check_db');
$router->post('/install/setup', [\NetFusion\App\Http\Controllers\InstallController::class, 'setup'])->name('install.setup');
$router->get('/install/completed', [\NetFusion\App\Http\Controllers\InstallController::class, 'completed'])->name('install.completed');

// ============================================================
//  Unified Master Executive Dashboard (Phase 24)
// ============================================================

$router->group(['prefix' => 'admin', 'name' => 'admin.'], function (Router $r) {
    $r->get('/dashboard', [\NetFusion\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    $r->get('/modules', function () {
        return \NetFusion\Core\Http\Response::redirect('/system/modules');
    });
    $r->get('/modules/upload', function () {
        return \NetFusion\Core\Http\Response::redirect('/system/modules/upload');
    });
    $r->get('/audit/deleted', function () {
        return \NetFusion\Core\Http\Response::redirect('/system/audit/deleted');
    });
    $r->post('/modules/toggle', [\NetFusion\App\Http\Controllers\DashboardController::class, 'toggleModule'])->name('modules.toggle');
    $r->post('/tenants/switch', [\NetFusion\App\Http\Controllers\DashboardController::class, 'switchTenant'])->name('tenants.switch');
    $r->post('/tenants/create', [\NetFusion\App\Http\Controllers\DashboardController::class, 'createTenant'])->name('tenants.create');
    $r->post('/tenants/update', [\NetFusion\App\Http\Controllers\DashboardController::class, 'updateTenant'])->name('tenants.update');
    $r->post('/settings/branding', [\NetFusion\App\Http\Controllers\DashboardController::class, 'updateBranding'])->name('settings.branding');
    $r->post('/users/create', [\NetFusion\App\Http\Controllers\DashboardController::class, 'createUser'])->name('users.create');
});

$router->get('/api/v1/dashboard/metrics', [\NetFusion\App\Http\Controllers\DashboardController::class, 'metrics'])->name('api.dashboard.metrics');

// ============================================================
//  DotNetBD License CMS (API Engine v5.1.8) Web GUI
// ============================================================

$router->get('/license', [\NetFusion\App\Http\Controllers\LicenseController::class, 'index'])->name('license.index');
$router->post('/license/activate', [\NetFusion\App\Http\Controllers\LicenseController::class, 'activate'])->name('license.activate');
$router->post('/license/check', [\NetFusion\App\Http\Controllers\LicenseController::class, 'check'])->name('license.check');
$router->post('/license/deactivate', [\NetFusion\App\Http\Controllers\LicenseController::class, 'deactivate'])->name('license.deactivate');

