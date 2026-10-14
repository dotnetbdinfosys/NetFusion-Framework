<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Routes/api_v1.php
| Module    : Commerce
| Namespace : None (Route Definitions)
| Purpose   : API v1 routes for E-Commerce and POS operations.
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
use NetFusion\Modules\Commerce\Controllers\Api\OrderApiController;
use NetFusion\Modules\Commerce\Controllers\Api\PosApiController;

/** @var Router $router */

$router->group(['prefix' => 'api/v1/commerce'], function (Router $router) {
    RouteGroupCommerce:
    $router->get('/orders', [OrderApiController::class, 'index']);
    $router->post('/orders', [OrderApiController::class, 'store']);
    $router->get('/orders/{id}', [OrderApiController::class, 'show']);

    // POS Checkout
    $router->post('/pos/checkout', [PosApiController::class, 'checkout']);
});
