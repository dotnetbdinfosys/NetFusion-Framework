<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Modules/Module.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : HMVC module representation holding directory paths, namespace, priority, and booted status.
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
| File      : core/Modules/Module.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : HMVC module representation holding directory paths, namespace, priority, and booted status.
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
| File      : core/Modules/Module.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : HMVC module representation holding directory paths, namespace, priority, and booted status.
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

/**
 * NetFusion Module Entity
 *
 * Represents an active, isolated HMVC module within the framework.
 *
 * @package NetFusion\Core\Modules
 * @version 1.0.0
 */
class Module
{
    /**
     * Whether this module has been booted by the framework.
     */
    private bool $booted = false;

    /**
     * @param string         $path     Absolute filesystem path to module folder.
     * @param ModuleManifest $manifest Parsed module.json manifest.
     * @param string         $namespace Base PHP namespace for this module.
     */
    public function __construct(
        private readonly string         $path,
        private readonly ModuleManifest $manifest,
        private readonly string         $namespace,
    ) {}

    /**
     * Get module slug identifier.
     */
    public function slug(): string
    {
        return $this->manifest->slug;
    }

    /**
     * Get human-readable module name.
     */
    public function name(): string
    {
        return $this->manifest->name;
    }

    /**
     * Get semantic version.
     */
    public function version(): string
    {
        return $this->manifest->version;
    }

    /**
     * Get brief description.
     */
    public function description(): string
    {
        return $this->manifest->description;
    }

    /**
     * Check if the module is enabled in its manifest.
     */
    public function isEnabled(): bool
    {
        return $this->manifest->enabled;
    }

    /**
     * Get module loading priority.
     */
    public function priority(): int
    {
        return $this->manifest->priority;
    }

    /**
     * Get module dependencies.
     *
     * @return array<int, string>
     */
    public function dependencies(): array
    {
        return $this->manifest->dependencies;
    }

    /**
     * Get registered service provider class names.
     *
     * @return array<int, string>
     */
    public function providers(): array
    {
        return $this->manifest->providers;
    }

    /**
     * Get the root directory path of this module.
     *
     * @param  string $append Optional sub-path.
     * @return string
     */
    public function path(string $append = ''): string
    {
        return $append !== ''
            ? rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . ltrim($append, '/\\')
            : $this->path;
    }

    /**
     * Get the base PHP namespace of this module.
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * Path to module routes directory.
     */
    public function routesPath(string $file = ''): string
    {
        $base = $this->path('Routes');
        return $file !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($file, '/\\') : $base;
    }

    /**
     * Path to module views directory.
     */
    public function viewsPath(string $file = ''): string
    {
        $base = $this->path('Views');
        return $file !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($file, '/\\') : $base;
    }

    /**
     * Path to module migrations directory.
     */
    public function migrationsPath(): string
    {
        return $this->path('Database' . DIRECTORY_SEPARATOR . 'Migrations');
    }

    /**
     * Path to module config file (if exists).
     */
    public function configPath(): string
    {
        return $this->path('config.php');
    }

    /**
     * Get the manifest instance.
     */
    public function manifest(): ModuleManifest
    {
        return $this->manifest;
    }

    /**
     * Check if module has been booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Mark module as booted.
     */
    public function markAsBooted(): void
    {
        $this->booted = true;
    }
}
