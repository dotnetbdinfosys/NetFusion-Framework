<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/ServiceProviderInterface.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Contract for 2-phase register and boot service provider lifecycle.
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
| File      : core/ServiceProviderInterface.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Contract for 2-phase register and boot service provider lifecycle.
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
| File      : core/ServiceProviderInterface.php
| Module    : Core
| Namespace : NetFusion\Core
| Purpose   : Contract for 2-phase register and boot service provider lifecycle.
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
 * NetFusion Service Provider Interface
 *
 * All module and core service providers must implement this interface.
 * The lifecycle is:
 *   1. register($app) — Bind services into the DI Container.
 *      Do NOT call other services here; only register bindings.
 *   2. boot($app)     — Boot the provider after ALL providers have
 *      been registered. Safe to use other services here.
 *
 * @package NetFusion\Core
 * @version 1.0.0
 */
interface ServiceProviderInterface
{
    /**
     * Register services into the DI Container.
     *
     * @param Application $app The application (DI Container).
     */
    public function register(Application $app): void;

    /**
     * Boot the service provider.
     * Called after all providers have been registered.
     *
     * @param Application $app The application (DI Container).
     */
    public function boot(Application $app): void;
}
