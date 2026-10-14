<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Accounting/Routes/web.php
| Module    : Accounting
| Namespace : None (Route Definitions)
| Purpose   : Web routes for the Accounting and Financial Ledger module.
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
use NetFusion\Modules\Accounting\Controllers\AccountingDashboardController;

/** @var Router $router */

$router->get('/accounting', [AccountingDashboardController::class, 'index']);
