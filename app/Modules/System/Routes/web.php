<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Routes/web.php
| Module    : Modules\System
| Namespace : None (Module Web Routes)
| Purpose   : Web routes for System HMVC module.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.3.5
|
|--------------------------------------------------------------------------
*/

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\System\Controllers\SystemController;

/** @var Router $router */

$router->get('/system', [SystemController::class, 'status'])->name('system.index');
$router->get('/system/status', [SystemController::class, 'status'])->name('system.status');
$router->get('/system/modules', [SystemController::class, 'modules'])->name('system.modules');
