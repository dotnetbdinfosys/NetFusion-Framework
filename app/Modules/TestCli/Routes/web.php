<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/TestCli/Routes/web.php
| Module    : Modules\TestCli
| Namespace : None (Module Web Routes)
| Purpose   : Web routing definitions for TestCli Module.
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
use NetFusion\Modules\TestCli\Controllers\TestCliController;

/** @var Router $router */
$router->get('/testcli', [TestCliController::class, 'index'])->name('testcli.index');