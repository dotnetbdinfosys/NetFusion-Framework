<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Routes/web.php
| Module    : Modules\HRM
| Namespace : None (Module Web Routes)
| Purpose   : Web routing definitions for HRM module dashboard and overview.
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
use NetFusion\Modules\HRM\Controllers\HrmDashboardController;

/** @var Router $router */
$router->get('/hrm', [HrmDashboardController::class, 'index'])->name('hrm.index');
