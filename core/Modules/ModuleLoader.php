<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Modules/ModuleLoader.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Zero-configuration dynamic auto-discovery engine scanning app/Modules/ and mounting routes.
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
| File      : core/Modules/ModuleLoader.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Zero-configuration dynamic auto-discovery engine scanning app/Modules/ and mounting routes.
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
| File      : core/Modules/ModuleLoader.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Zero-configuration dynamic auto-discovery engine scanning app/Modules/ and mounting routes.
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

/**
 * NetFusion Dynamic Module Loader
 *
 * Scans, registers, and boots isolated HMVC modules from app/Modules/.
 * Supports hot-pluggable modules, priority-based boot ordering,
 * automatic route and config discovery, and service provider execution.
 *
 * @package NetFusion\Core\Modules
 * @version 1.0.0
 */
class ModuleLoader
{
    /**
     * Whether discovery has already run.
     */
    private bool $discovered = false;

    /**
     * @param Application      $app
     * @param ModuleRepository $repository
     */
    public function __construct(
        private readonly Application      $app,
        private readonly ModuleRepository $repository,
    ) {}

    /**
     * Discover all modules in the configured modules path and register them.
     */
    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $modulesPath = $this->app->modulesPath();

        if (!is_dir($modulesPath)) {
            $this->discovered = true;
            return;
        }

        $manifestFile = (string) config('modules.manifest_file', 'module.json');
        $disabledList = (array) config('modules.disabled', []);
        $explicitList = (array) config('modules.modules', []);

        $dirs = scandir($modulesPath);
        if ($dirs === false) {
            $this->discovered = true;
            return;
        }

        $discoveredModules = [];

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $moduleDir = $modulesPath . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($moduleDir)) {
                continue;
            }

            $manifestPath = $moduleDir . DIRECTORY_SEPARATOR . $manifestFile;
            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $manifest = ModuleManifest::fromFile($manifestPath, $dir);
            } catch (\Throwable $e) {
                // Log or skip malformed module manifest
                continue;
            }

            // Check if explicitly disabled or inactive in manifest
            if (!$manifest->enabled || in_array($manifest->slug, $disabledList, true) || in_array($dir, $disabledList, true)) {
                continue;
            }

            // If an explicit load list is specified, only include modules in that list
            if (!empty($explicitList) && !in_array($manifest->slug, $explicitList, true) && !in_array($dir, $explicitList, true)) {
                continue;
            }

            $namespace = 'NetFusion\\Modules\\' . $dir;
            $module = new Module($moduleDir, $manifest, $namespace);

            $discoveredModules[] = $module;
        }

        // Sort modules by priority (lower number = higher priority)
        usort($discoveredModules, fn(Module $a, Module $b) => $a->priority() <=> $b->priority());

        foreach ($discoveredModules as $module) {
            $this->repository->register($module);
        }

        $this->discovered = true;
    }

    /**
     * Reset discovery cache so new modules can be registered dynamically.
     */
    public function reset(): void
    {
        $this->discovered = false;
    }

    /**
     * Register service providers and module configurations for all discovered modules.
     */
    public function registerModules(): void
    {
        $this->discover();

        $autoConfig = config('modules.auto_discover.config', true);

        foreach ($this->repository->enabled() as $module) {
            // 1. Merge module config if present
            if ($autoConfig && is_file($module->configPath())) {
                $this->app->make('config')->merge($module->slug(), require $module->configPath());
            }

            // 2. Register Service Providers
            $providers = $this->resolveModuleProviders($module);
            foreach ($providers as $providerClass) {
                if (class_exists($providerClass)) {
                    $provider = $this->app->make($providerClass);
                    if ($provider instanceof ModuleServiceProvider) {
                        $provider->setModule($module);
                    }
                    $this->app->register($provider);
                }
            }
        }
    }

    /**
     * Auto-discover and register routes for all enabled modules.
     * Called by the HTTP Kernel during route bootstrapping.
     */
    public function loadModuleRoutes(Router $router): void
    {
        $this->discover();

        $autoRoutes = config('modules.auto_discover.routes', true);
        if (!$autoRoutes) {
            return;
        }

        foreach ($this->repository->enabled() as $module) {
            $webRoutes = $module->routesPath('web.php');
            if (is_file($webRoutes)) {
                $router->loadFile($webRoutes);
            }

            $apiRoutes = $module->routesPath('api.php');
            if (is_file($apiRoutes)) {
                // Group module API routes under /api/{module_slug}
                $router->group(['prefix' => 'api/' . $module->slug()], function (Router $r) use ($apiRoutes) {
                    $r->loadFile($apiRoutes);
                });
            }

            // Auto-load versioned route files: api_v1.php, api_v2.php etc.
            $routesDir = $module->path('Routes');
            if (is_dir($routesDir)) {
                $versionedFiles = glob($routesDir . DIRECTORY_SEPARATOR . 'api_v*.php') ?: [];
                foreach ($versionedFiles as $vFile) {
                    $router->loadFile($vFile);
                }
            }
        }
    }


    /**
     * Resolve the provider class names for a module.
     *
     * @param  Module $module
     * @return array<int, string>
     */
    private function resolveModuleProviders(Module $module): array
    {
        $providers = $module->providers();

        // If no providers specified in manifest, try the standard convention
        if (empty($providers)) {
            $conventionalClass = $module->namespace() . '\\Providers\\' . basename($module->path()) . 'ServiceProvider';
            if (class_exists($conventionalClass)) {
                $providers[] = $conventionalClass;
            } else {
                $genericClass = $module->namespace() . '\\Providers\\ModuleServiceProvider';
                if (class_exists($genericClass)) {
                    $providers[] = $genericClass;
                }
            }
        }

        return $providers;
    }
}
