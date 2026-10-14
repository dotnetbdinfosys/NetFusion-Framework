<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Inventory/Providers/InventoryServiceProvider.php
| Module    : Inventory
| Namespace : NetFusion\Modules\Inventory\Providers
| Purpose   : Service provider bootstrapping inventory tracking and supply chain routes.
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

namespace NetFusion\Modules\Inventory\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;
use NetFusion\Modules\Inventory\Services\StockService;

/**
 * Class InventoryServiceProvider
 *
 * @package NetFusion\Modules\Inventory\Providers
 */
class InventoryServiceProvider extends ModuleServiceProvider
{
    /**
     * Register inventory domain services.
     */
    public function register(Application $app): void
    {
        $app->singleton(StockService::class, function () use ($app) {
            return new StockService($app);
        });
    }

    /**
     * Boot inventory routes.
     */
    public function boot(Application $app): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api_v1.php');
    }
}
