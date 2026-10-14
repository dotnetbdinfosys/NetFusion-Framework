<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Routes/api.php
| Module    : Modules\System
| Namespace : None (Module API Routes)
| Purpose   : API routes for System HMVC module mounted at /api/system.
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
| File      : app/Modules/System/Routes/api.php
| Module    : Modules\System
| Namespace : None (Module API Routes)
| Purpose   : API routes for System HMVC module mounted at /api/system.
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
| File      : app/Modules/System/Routes/api.php
| Module    : Modules\System
| Namespace : None (Module API Routes)
| Purpose   : API routes for System HMVC module mounted at /api/system. Also registers global /api/docs.
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
use NetFusion\Modules\System\Controllers\OpenApiController;

/**
 * System Module API Routes
 *
 * Automatically prefixed with /api/system by the ModuleLoader.
 *
 * @var Router $router
 */

$router->get('/ping', function () {
    return Response::json([
        'module' => 'system',
        'pong'   => true,
        'time'   => now()->format(\DateTimeInterface::ATOM),
    ]);
})->name('api.system.ping');

// ── OpenAPI Documentation Routes (registered globally without /api/system prefix) ──
// These routes are registered directly on the root /api prefix
// via a group override, effectively at /api/docs and /api/docs/ui.
$router->group(['prefix' => ''], function (Router $r) {
    $r->get('/docs',    [OpenApiController::class, 'spec'])->name('api.docs.spec');
    $r->get('/docs/ui', [OpenApiController::class, 'ui'])->name('api.docs.ui');
});
