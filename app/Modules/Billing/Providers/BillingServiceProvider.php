<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/Billing/Providers/BillingServiceProvider.php
| Module    : Modules\Billing
| Namespace : NetFusion\Modules\Billing\Providers
| Purpose   : Service provider managing lifecycle, dependencies, and boot hooks for Billing Module.
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

namespace NetFusion\Modules\Billing\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    public function register(Application $app): void
    {
        // Bind module services here
    }

    public function boot(Application $app): void
    {
        // Boot module services, events, or listeners
    }
}