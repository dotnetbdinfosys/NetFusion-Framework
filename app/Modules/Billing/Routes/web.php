<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Routes/web.php
| Module    : Modules\Billing
| Namespace : None (Module Web Routes)
| Purpose   : Web routing definitions for Billing Module dashboard and overview.
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
use NetFusion\Modules\Billing\Controllers\BillingController;

/** @var Router $router */
$router->get('/billing', [BillingController::class, 'index'])->name('billing.index');