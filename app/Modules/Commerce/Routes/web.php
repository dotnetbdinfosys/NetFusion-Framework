<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Routes/web.php
| Module    : Commerce
| Namespace : None (Route Definitions)
| Purpose   : Web routes for Commerce and POS management.
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
use NetFusion\Modules\Commerce\Controllers\CommerceDashboardController;

/** @var Router $router */

$router->get('/commerce', [CommerceDashboardController::class, 'index']);
