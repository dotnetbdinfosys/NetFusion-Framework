<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Routes/api_v1.php
| Module    : Inventory
| Namespace : None (Route Definitions)
| Purpose   : Versioned REST API routes for Inventory products and stock movements.
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
use NetFusion\Modules\Inventory\Controllers\Api\ProductApiController;
use NetFusion\Modules\Inventory\Controllers\Api\StockApiController;

/** @var Router $router */

$router->group(['prefix' => 'api/v1/inventory'], function (Router $router) {
    // Product Catalog
    $router->get('/products', [ProductApiController::class, 'index']);
    $router->post('/products', [ProductApiController::class, 'store']);
    $router->get('/products/{id}', [ProductApiController::class, 'show']);

    // Stock Movements
    $router->post('/stocks/receive', [StockApiController::class, 'receive']);
    $router->post('/stocks/issue', [StockApiController::class, 'issue']);
    $router->post('/stocks/transfer', [StockApiController::class, 'transfer']);
    $router->get('/stocks/valuation', [StockApiController::class, 'valuation']);
});
