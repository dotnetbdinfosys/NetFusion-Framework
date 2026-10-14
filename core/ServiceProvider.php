<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/ServiceProvider.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Abstract base service provider with default no-op register and boot hooks.
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
| File      : core/ServiceProvider.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Abstract base service provider with default no-op register and boot hooks.
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
| File      : core/ServiceProvider.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Abstract base service provider with default no-op register and boot hooks.
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

namespace NetFusion\Core;

use NetFusion\Core\Application;

/**
 * NetFusion Abstract Service Provider
 *
 * A convenient base class for service providers. Provides no-op
 * implementations of register() and boot() so that providers
 * only need to override the methods they actually use.
 *
 * @package NetFusion\Core
 * @version 1.0.0
 */
abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     *
     * Override this in your provider to bind services into the container.
     */
    public function register(Application $app): void
    {
        // No-op by default. Override in subclasses.
    }

    /**
     * {@inheritDoc}
     *
     * Override this in your provider to perform boot-time actions
     * (e.g., loading routes, registering event listeners, loading views).
     */
    public function boot(Application $app): void
    {
        // No-op by default. Override in subclasses.
    }
}
