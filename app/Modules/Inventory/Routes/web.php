<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Routes/web.php
| Module    : Inventory
| Namespace : None (Route Definitions)
| Purpose   : Web routes for Inventory and Warehouse Management module.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\Inventory\Controllers\InventoryDashboardController;

/** @var Router $router */

$router->get('/inventory', [InventoryDashboardController::class, 'index'])->name('inventory.dashboard');
$router->get('/inventory/products', [InventoryDashboardController::class, 'products'])->name('inventory.products');
$router->get('/inventory/warehouses', [InventoryDashboardController::class, 'warehouses'])->name('inventory.warehouses');
$router->get('/inventory/stock-in', [InventoryDashboardController::class, 'stockIn'])->name('inventory.stock-in');
$router->get('/inventory/stock-out', [InventoryDashboardController::class, 'stockOut'])->name('inventory.stock-out');
$router->get('/inventory/low-stock', [InventoryDashboardController::class, 'lowStock'])->name('inventory.low-stock');
$router->get('/inventory/valuation', [InventoryDashboardController::class, 'valuation'])->name('inventory.valuation');
