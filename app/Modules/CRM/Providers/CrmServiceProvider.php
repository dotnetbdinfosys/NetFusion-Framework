<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Providers/CrmServiceProvider.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Providers
| Purpose   : Service provider bootstrapping CRM pipeline and helpdesk routes.
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

namespace NetFusion\Modules\CRM\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;
use NetFusion\Modules\CRM\Services\CrmService;
use NetFusion\Modules\CRM\Services\TicketService;

class CrmServiceProvider extends ModuleServiceProvider
{
    public function register(Application $app): void
    {
        $app->singleton(CrmService::class, function () use ($app) {
            return new CrmService($app);
        });

        $app->singleton(TicketService::class, function () use ($app) {
            return new TicketService($app);
        });
    }

    public function boot(Application $app): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api_v1.php');
    }
}
