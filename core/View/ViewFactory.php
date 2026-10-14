<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/View/ViewFactory.php
| Module    : Core\View
| Namespace : NetFusion\Core\View
| Purpose   : Factory resolving view paths across global and HMVC module namespaces.
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

namespace NetFusion\Core\View;

use NetFusion\Core\Application;
use RuntimeException;

/**
 * NetFusion View Factory
 *
 * Coordinates view resolution, module view namespaces, shared variables,
 * and View instance generation.
 *
 * @package NetFusion\Core\View
 * @version 1.0.0
 */
class ViewFactory
{
    /**
     * Primary view lookup directory paths.
     *
     * @var string[]
     */
    protected array $paths = [];

    /**
     * Named module view namespaces (e.g. 'system' => 'app/Modules/System/Views').
     *
     * @var array<string, string>
     */
    protected array $namespaces = [];

    /**
     * Global data shared with all rendered views.
     *
     * @var array<string, mixed>
     */
    protected array $shared = [];

    /**
     * @param Application $app
     */
    public function __construct(
        protected readonly Application $app
    ) {
        $this->paths[] = $this->app->viewsPath();
    }

    /**
     * Add a directory path to the global search locations.
     */
    public function addPath(string $path): static
    {
        if (is_dir($path)) {
            $this->paths[] = $path;
        }
        return $this;
    }

    /**
     * Register a named HMVC module view namespace.
     *
     * @param string $namespace e.g. 'billing', 'system'
     * @param string $path      Directory containing module views
     */
    public function addNamespace(string $namespace, string $path): static
    {
        $this->namespaces[strtolower($namespace)] = rtrim($path, '/\\');
        return $this;
    }

    /**
     * Share a piece of data across all views.
     */
    public function share(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->shared = array_merge($this->shared, $key);
        } else {
            $this->shared[$key] = $value;
        }
        return $this;
    }

    /**
     * Get all shared data.
     *
     * @return array<string, mixed>
     */
    public function getSharedData(): array
    {
        return $this->shared;
    }

    /**
     * Create a new View instance.
     *
     * @param  string               $view Dot-notated view name or namespace::view.
     * @param  array<string, mixed> $data
     * @return View
     */
    public function make(string $view, array $data = []): View
    {
        $filePath = $this->resolvePath($view);
        return new View($this, $filePath, $data);
    }

    /**
     * Render a view and return the HTML output string.
     */
    public function render(string $view, array $data = []): string
    {
        return $this->make($view, $data)->render();
    }

    /**
     * Resolve the absolute filesystem path to a view template.
     *
     * Supports:
     *   - Namespace views: 'billing::invoices.show'
     *   - Global views: 'home', 'layouts.app', 'users.profile'
     *
     * @param  string $view
     * @return string
     *
     * @throws RuntimeException
     */
    public function resolvePath(string $view): string
    {
        // 1. Check for module namespace syntax: 'namespace::view.name'
        if (str_contains($view, '::')) {
            [$namespace, $viewName] = explode('::', $view, 2);
            $namespace = strtolower($namespace);

            $baseDir = $this->namespaces[$namespace] ?? null;

            // If not explicitly registered, auto-resolve from app/Modules/{Namespace}/Views
            if ($baseDir === null) {
                $autoPath = $this->app->modulesPath(ucfirst($namespace) . DIRECTORY_SEPARATOR . 'Views');
                if (is_dir($autoPath)) {
                    $baseDir = $autoPath;
                    $this->addNamespace($namespace, $autoPath);
                }
            }

            if ($baseDir === null) {
                throw new RuntimeException("No view namespace registered for [{$namespace}].");
            }

            $relativePath = str_replace('.', DIRECTORY_SEPARATOR, $viewName) . '.php';
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $relativePath;

            if (file_exists($fullPath)) {
                return $fullPath;
            }

            throw new RuntimeException("View [{$view}] not found at [{$fullPath}].");
        }

        // 2. Global view paths
        $relativePath = str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';

        foreach ($this->paths as $path) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $relativePath;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        throw new RuntimeException("View [{$view}] not found in registered view paths.");
    }
}
