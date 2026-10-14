<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Modules/ModuleServiceProvider.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Base service provider with convenience methods for loading module routes, views, and configs.
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
| File      : core/Modules/ModuleServiceProvider.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Base service provider with convenience methods for loading module routes, views, and configs.
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
| File      : core/Modules/ModuleServiceProvider.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Base service provider with convenience methods for loading module routes, views, and configs.
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

namespace NetFusion\Core\Modules;

use NetFusion\Core\Application;
use NetFusion\Core\Routing\Router;
use NetFusion\Core\ServiceProvider;

/**
 * NetFusion Base Module Service Provider
 *
 * Provides HMVC modules with declarative helpers to register routes,
 * configuration, views, and migrations during framework boot.
 *
 * @package NetFusion\Core\Modules
 * @version 1.0.0
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Associated Module instance.
     */
    protected ?Module $module = null;

    /**
     * Bind the Module instance to this provider.
     */
    public function setModule(Module $module): void
    {
        $this->module = $module;
    }

    /**
     * Get the associated Module instance.
     */
    public function getModule(): ?Module
    {
        return $this->module;
    }

    /**
     * Load routes from a file into the router.
     *
     * @param string $path Absolute path to PHP routes file.
     */
    protected function loadRoutesFrom(string $path): void
    {
        if (is_file($path)) {
            /** @var Router $router */
            $router = app(Router::class);
            $router->loadFile($path);
        }
    }

    /**
     * Load Web routes for this module.
     * Automatically applies a route group with prefix matching the module slug if desired.
     *
     * @param string      $path   Absolute path to routes file.
     * @param string|null $prefix Optional URI prefix (defaults to empty or module slug).
     */
    protected function loadWebRoutesFrom(string $path, ?string $prefix = null): void
    {
        if (!is_file($path)) {
            return;
        }

        /** @var Router $router */
        $router = app(Router::class);

        if ($prefix !== null && $prefix !== '') {
            $router->group(['prefix' => $prefix], function (Router $r) use ($path) {
                $r->loadFile($path);
            });
        } else {
            $router->loadFile($path);
        }
    }

    /**
     * Load API routes for this module under `/api/{prefix}`.
     *
     * @param string $path   Absolute path to API routes file.
     * @param string $prefix Sub-prefix after `/api` (e.g. 'system' -> `/api/system`).
     */
    protected function loadApiRoutesFrom(string $path, string $prefix = ''): void
    {
        if (!is_file($path)) {
            return;
        }

        /** @var Router $router */
        $router = app(Router::class);

        $fullPrefix = 'api' . ($prefix !== '' ? '/' . trim($prefix, '/') : '');

        $router->group(['prefix' => $fullPrefix], function (Router $r) use ($path) {
            $r->loadFile($path);
        });
    }

    /**
     * Merge a module config file into the central Config manager.
     *
     * @param string $path Absolute path to config file.
     * @param string $key  Top-level config key / namespace.
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!is_file($path)) {
            return;
        }

        $config = require $path;
        if (is_array($config)) {
            app('config')->merge($key, $config);
        }
    }
}
