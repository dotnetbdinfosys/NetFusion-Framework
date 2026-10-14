<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Routes/web.php
| Module    : Modules\System
| Namespace : None (Module Web Routes)
| Purpose   : Web routes for System HMVC module.
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
| File      : app/Modules/System/Routes/web.php
| Module    : Modules\System
| Namespace : None (Module Web Routes)
| Purpose   : Web routes for System HMVC module.
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
| File      : app/Modules/System/Routes/web.php
| Module    : Modules\System
| Namespace : None (Module Web Routes)
| Purpose   : Web routes for System HMVC module.
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
use NetFusion\Modules\System\Controllers\SystemController;

/**
 * System Module Web Routes
 *
 * @var Router $router
 */

$router->get('/system/status', [SystemController::class, 'status'])->name('system.status');
$router->get('/system/modules', [SystemController::class, 'modules'])->name('system.modules');
