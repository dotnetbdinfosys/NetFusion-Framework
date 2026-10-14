<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Providers/SystemServiceProvider.php
| Module    : Modules\System
| Namespace : NetFusion\Modules\System\Providers
| Purpose   : Service provider for System HMVC Module.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Providers/SystemServiceProvider.php
| Module    : Modules\System
| Namespace : NetFusion\Modules\System\Providers
| Purpose   : Service provider for System HMVC Module.
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

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Providers/SystemServiceProvider.php
| Module    : Modules\System
| Namespace : NetFusion\Modules\System\Providers
| Purpose   : Service provider for System HMVC Module.
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

namespace NetFusion\Modules\System\Providers;

use NetFusion\Core\Application;
use NetFusion\Core\Modules\ModuleServiceProvider;

/**
 * System Module Service Provider
 *
 * Demonstrates module service provider lifecycle.
 */
class SystemServiceProvider extends ModuleServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register(Application $app): void
    {
        // Bind module-specific services or singletons into the container
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Application $app): void
    {
        // Module boot actions
    }
}
