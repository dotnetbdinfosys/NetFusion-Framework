<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/TestCli/Routes/api.php
| Module    : Modules\TestCli
| Namespace : None (Module API Routes)
| Purpose   : REST API routing definitions for TestCli Module (mounted at /api/testcli).
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

/** @var Router $router */
$router->get('/status', function () {
    return Response::json([
        'module' => 'testcli',
        'api'    => 'active',
    ]);
})->name('api.testcli.status');