<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Routes/web.php
| Module    : CRM
| Namespace : None (Route Definitions)
| Purpose   : Web routes for CRM pipeline and Helpdesk overview.
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
use NetFusion\Modules\CRM\Controllers\CrmDashboardController;

/** @var Router $router */

$router->get('/crm', [CrmDashboardController::class, 'index']);
