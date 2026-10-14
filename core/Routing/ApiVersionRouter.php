<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Routing/ApiVersionRouter.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Handles API version routing groups and versioned module route discovery.
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

namespace NetFusion\Core\Routing;

use Closure;

/**
 * NetFusion API Version Router
 *
 * Provides dedicated API version prefixing (e.g. /api/v1, /api/v2) and auto-discovery
 * for versioned route files across core and modules.
 *
 * @package NetFusion\Core\Routing
 * @version 1.0.0
 */
class ApiVersionRouter
{
    /**
     * Supported API versions.
     *
     * @var array<string>
     */
    protected array $supportedVersions = ['v1', 'v2'];

    /**
     * Default API version.
     */
    protected string $defaultVersion = 'v1';

    /**
     * @param Router $router Underlying Router instance.
     */
    public function __construct(
        protected readonly Router $router,
    ) {}

    /**
     * Register a route group scoped to a specific API version.
     *
     * @param string  $version  API version (e.g., 'v1', 'v2').
     * @param Closure $callback Closure registering routes.
     */
    public function version(string $version, Closure $callback): void
    {
        $version = strtolower(ltrim($version, '/'));

        $this->router->group([
            'prefix' => 'api/' . $version,
            'name'   => 'api.' . $version . '.',
        ], $callback);
    }

    /**
     * Get supported versions.
     *
     * @return array<string>
     */
    public function getSupportedVersions(): array
    {
        return $this->supportedVersions;
    }

    /**
     * Set supported versions.
     *
     * @param array<string> $versions
     */
    public function setSupportedVersions(array $versions): void
    {
        $this->supportedVersions = $versions;
    }

    /**
     * Get default version.
     */
    public function getDefaultVersion(): string
    {
        return $this->defaultVersion;
    }
}
