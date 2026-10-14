<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : routes/api.php
| Module    : Routing
| Namespace : None (Global API Routes)
| Purpose   : Global RESTful API routes with automatic /api prefixing.
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
| File      : routes/api.php
| Module    : Routing
| Namespace : None (Global API Routes)
| Purpose   : Global RESTful API routes with automatic /api prefixing.
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
| File      : routes/api.php
| Module    : Routing
| Namespace : None (Global API Routes)
| Purpose   : Global RESTful API routes with automatic /api prefixing.
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
 * NetFusion API Routes
 *
 * All routes here are automatically prefixed with /api by the Kernel.
 * Use these for RESTful JSON API endpoints.
 *
 * In Phase 6, the 'api' middleware group (JWT auth, throttling) will
 * be applied automatically.
 *
 * @var Router $router
 */

// ============================================================
//  API Status / Version
// ============================================================

$router->get('/status', function () {
    return Response::json([
        'api'         => 'NetFusion API',
        'version'     => 'v1',
        'framework'   => app()->version(),
        'status'      => 'operational',
        'environment' => app()->environment(),
        'timestamp'   => now()->format(\DateTimeInterface::ATOM),
        'uptime_ms'   => app()->elapsedTime(),
    ]);
})->name('api.status');

// ============================================================
//  API v1 Group
// ============================================================

$router->group(['prefix' => 'v1', 'name' => 'api.v1.'], function (Router $r) {

    // --- Framework info endpoint
    $r->get('/info', function () {
        return Response::json([
            'name'     => config('app.name'),
            'version'  => app()->version(),
            'php'      => PHP_VERSION,
            'timezone' => config('app.timezone'),
            'debug'    => config('app.debug'),
        ]);
    })->name('info');

    // --- Echo endpoint (useful for API testing)
    $r->post('/echo', function (\NetFusion\Core\Http\Request $request) {
        return Response::json([
            'method'  => $request->method(),
            'headers' => $request->headers(),
            'body'    => $request->all(),
            'ip'      => $request->ip(),
        ]);
    })->name('echo');

    // --- Demo: Named resource route under API
    $r->get('/users/{id}', function (\NetFusion\Core\Http\Request $request) {
        $id = $request->route('id');
        return Response::json([
            'data' => [
                'id'    => (int) $id,
                'name'  => 'Demo User #' . $id,
                'email' => 'user' . $id . '@example.com',
            ],
        ]);
    })->whereNumber('id')->name('users.show');

    // --- Incoming Webhook Gateway Endpoint
    $r->post('/webhooks/incoming/{gateway}', [\NetFusion\App\Http\Controllers\IncomingWebhookController::class, 'handle'])
      ->name('webhooks.incoming');

    // --- Real-Time Event Broadcasting & SSE Stream
    $r->get('/broadcasting/stream', [\NetFusion\App\Http\Controllers\BroadcastStreamController::class, 'stream'])
      ->name('broadcasting.stream');
    $r->post('/broadcasting/auth', [\NetFusion\App\Http\Controllers\BroadcastStreamController::class, 'auth'])
      ->name('broadcasting.auth');

});
