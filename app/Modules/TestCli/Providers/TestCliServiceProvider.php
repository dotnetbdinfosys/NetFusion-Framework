<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/TestCli/Providers/TestCliServiceProvider.php
| Module    : Modules\TestCli
| Namespace : NetFusion\Modules\TestCli\Providers
| Purpose   : Service provider managing lifecycle, dependencies, and boot hooks for TestCli Module.
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

namespace NetFusion\Modules\TestCli\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;

class TestCliServiceProvider extends ModuleServiceProvider
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