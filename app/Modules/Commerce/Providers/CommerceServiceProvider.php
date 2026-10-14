<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Commerce/Providers/CommerceServiceProvider.php
| Module    : Commerce
| Namespace : NetFusion\Modules\Commerce\Providers
| Purpose   : Service provider bootstrapping POS, E-Commerce and sales pipeline.
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

namespace NetFusion\Modules\Commerce\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;
use NetFusion\Modules\Commerce\Services\CommerceService;

class CommerceServiceProvider extends ModuleServiceProvider
{
    public function register(Application $app): void
    {
        $app->singleton(CommerceService::class, function () use ($app) {
            return new CommerceService($app);
        });
    }

    public function boot(Application $app): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api_v1.php');
    }
}
