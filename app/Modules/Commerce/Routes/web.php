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
| Purpose   : Web routes for E-Commerce and POS module.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.3.5
|
|--------------------------------------------------------------------------
*/

use NetFusion\Core\Routing\Router;
use NetFusion\Modules\Commerce\Controllers\CommerceDashboardController;

/** @var Router $router */

$router->get('/commerce', [CommerceDashboardController::class, 'index'])->name('commerce.dashboard');
$router->get('/commerce/orders', [CommerceDashboardController::class, 'orders'])->name('commerce.orders');
$router->get('/commerce/products', [CommerceDashboardController::class, 'products'])->name('commerce.products');
$router->get('/commerce/customers', [CommerceDashboardController::class, 'customers'])->name('commerce.customers');
$router->get('/commerce/payments', [CommerceDashboardController::class, 'payments'])->name('commerce.payments');
$router->get('/commerce/coupons', [CommerceDashboardController::class, 'coupons'])->name('commerce.coupons');
$router->get('/commerce/reports', [CommerceDashboardController::class, 'reports'])->name('commerce.reports');
