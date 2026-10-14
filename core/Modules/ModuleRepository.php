<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Modules/ModuleRepository.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : In-memory registry of all discovered and loaded HMVC modules.
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
| File      : core/Modules/ModuleRepository.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : In-memory registry of all discovered and loaded HMVC modules.
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
| File      : core/Modules/ModuleRepository.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : In-memory registry of all discovered and loaded HMVC modules.
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

/**
 * NetFusion Module Repository
 *
 * In-memory registry of all discovered and loaded HMVC modules.
 *
 * @package NetFusion\Core\Modules
 * @version 1.0.0
 */
class ModuleRepository
{
    /**
     * Storage of registered modules indexed by slug.
     *
     * @var array<string, Module>
     */
    private array $modules = [];

    /**
     * Register a module into the repository.
     */
    public function register(Module $module): void
    {
        $this->modules[$module->slug()] = $module;
    }

    /**
     * Retrieve a module by its slug.
     */
    public function get(string $slug): ?Module
    {
        return $this->modules[$slug] ?? null;
    }

    /**
     * Check if a module is registered.
     */
    public function has(string $slug): bool
    {
        return isset($this->modules[$slug]);
    }

    /**
     * Check if a registered module is currently enabled.
     */
    public function isEnabled(string $slug): bool
    {
        return isset($this->modules[$slug]) && $this->modules[$slug]->isEnabled();
    }

    /**
     * Return all registered modules.
     *
     * @return array<string, Module>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Return only enabled modules.
     *
     * @return array<string, Module>
     */
    public function enabled(): array
    {
        return array_filter($this->modules, fn(Module $m) => $m->isEnabled());
    }

    /**
     * Get the total count of registered modules.
     */
    public function count(): int
    {
        return count($this->modules);
    }
}
