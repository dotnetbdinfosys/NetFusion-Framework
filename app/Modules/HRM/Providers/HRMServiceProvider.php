<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/HRM/Providers/HRMServiceProvider.php
| Module    : Modules\HRM
| Namespace : NetFusion\Modules\HRM\Providers
| Purpose   : Service provider bootstrapping routes, views, and bindings for HRM.
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

namespace NetFusion\Modules\HRM\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;

/**
 * NetFusion HRM Service Provider
 *
 * Bootstraps routes, views, and services for the HRM & Payroll module.
 *
 * @package NetFusion\Modules\HRM\Providers
 * @version 1.0.0
 */
class HRMServiceProvider extends ModuleServiceProvider
{
    /**
     * Register services.
     */
    public function register(Application $app): void
    {
        // Custom DI bindings for HRM
    }

    /**
     * Bootstrap module services.
     */
    public function boot(Application $app): void
    {
        // Module routes and views are automatically discovered by ModuleLoader
    }
}
