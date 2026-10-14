<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Routing/RouteCollection.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Registry storing registered routes indexed by method with named route fast lookups.
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
| File      : core/Routing/RouteCollection.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Registry storing registered routes indexed by method with named route fast lookups.
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
| File      : core/Routing/RouteCollection.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Registry storing registered routes indexed by method with named route fast lookups.
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

/**
 * NetFusion Route Collection
 *
 * Maintains the registry of all registered routes and provides
 * fast lookup mechanisms:
 *  - Match by HTTP method + URI path
 *  - Lookup by route name (for URL generation)
 *  - Method-not-allowed detection (405 vs 404)
 *
 * @package NetFusion\Core\Routing
 * @version 1.0.0
 */
class RouteCollection
{
    /**
     * All registered routes indexed by HTTP method.
     *
     * @var array<string, Route[]>  e.g., ['GET' => [Route, ...], 'POST' => [...]]
     */
    private array $routes = [];

    /**
     * Named route lookup index.
     *
     * @var array<string, Route>  e.g., ['users.show' => Route]
     */
    private array $namedRoutes = [];

    /**
     * Add a route to the collection.
     *
     * @param Route $route The route to register.
     */
    public function add(Route $route): void
    {
        foreach ($route->getMethods() as $method) {
            $this->routes[$method][] = $route;
        }

        // Register in named route index
        if ($name = $route->getName()) {
            $this->namedRoutes[$name] = $route;
        }
    }

    /**
     * Update the named route index after a route is named post-registration.
     * Called by the Router when ->name() is chained on a route.
     *
     * @param string $name  The route name.
     * @param Route  $route The route instance.
     */
    public function updateNamedRoute(string $name, Route $route): void
    {
        $this->namedRoutes[$name] = $route;
    }

    /**
     * Match a request URI and method to a registered route.
     *
     * @param  string $method The HTTP method (uppercase).
     * @param  string $path   The request URI path (without query string).
     * @return RouteMatch     The match result (found | method_not_allowed | not_found).
     */
    public function match(string $method, string $path): RouteMatch
    {
        $method         = strtoupper($method);
        $methodRoutes   = $this->routes[$method] ?? [];
        $allowedMethods = [];

        // First pass: try routes registered for this exact method
        foreach ($methodRoutes as $route) {
            $params = $route->match($path);
            if ($params !== false) {
                return RouteMatch::found($route, $params);
            }
        }

        // Second pass: check all other methods to detect 405 vs 404
        foreach ($this->routes as $routeMethod => $routes) {
            if ($routeMethod === $method) {
                continue;
            }
            foreach ($routes as $route) {
                if ($route->match($path) !== false) {
                    $allowedMethods[] = $routeMethod;
                }
            }
        }

        if (!empty($allowedMethods)) {
            return RouteMatch::methodNotAllowed(array_unique($allowedMethods));
        }

        return RouteMatch::notFound();
    }

    /**
     * Get a route by its name.
     *
     * @param  string     $name The route name.
     * @return Route|null       The named route, or null if not found.
     */
    public function getByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Get all registered routes.
     *
     * @return array<string, Route[]>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Get all routes as a flat array.
     *
     * @return Route[]
     */
    public function flatten(): array
    {
        return array_merge(...array_values($this->routes));
    }

    /**
     * Get all named routes.
     *
     * @return array<string, Route>
     */
    public function named(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Check if any route is registered for a given URI path.
     *
     * @param  string $path The URI path.
     * @return bool
     */
    public function hasRoute(string $path): bool
    {
        foreach ($this->routes as $routes) {
            foreach ($routes as $route) {
                if ($route->match($path) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get the total number of registered routes.
     */
    public function count(): int
    {
        return array_sum(array_map('count', $this->routes));
    }
}
