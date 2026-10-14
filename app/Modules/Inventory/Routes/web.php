<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Routes/web.php
| Module    : Inventory
| Namespace : None (Route Definitions)
| Purpose   : Web routes for the Inventory and Supply Chain module.
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
use NetFusion\Modules\Inventory\Controllers\InventoryDashboardController;

/** @var Router $router */

$router->get('/inventory', [InventoryDashboardController::class, 'index']);
