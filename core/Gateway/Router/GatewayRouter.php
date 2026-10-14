<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Gateway\Router;

use NetFusion\Core\Gateway\Models\GatewayRequest;
use NetFusion\Core\Gateway\Models\Route;

/**
 * Gateway Router — matches an incoming GatewayRequest to the highest-priority
 * registered Route. Routes are sorted ascending by priority (lowest integer wins).
 */
class GatewayRouter
{
    /** @var Route[] */
    private array $routes = [];
    private bool  $sorted = false;

    /**
     * Registers a route with the router.
     */
    public function addRoute(Route $route): self
    {
        $this->routes[] = $route;
        $this->sorted   = false;
        return $this;
    }

    /**
     * Registers multiple routes at once.
     *
     * @param Route[] $routes
     */
    public function addRoutes(array $routes): self
    {
        foreach ($routes as $route) {
            $this->addRoute($route);
        }
        return $this;
    }

    /**
     * Finds the first matching Route for a request, or null if none match.
     */
    public function match(GatewayRequest $request): ?Route
    {
        $this->ensureSorted();

        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Returns all registered routes.
     *
     * @return Route[]
     */
    public function getRoutes(): array
    {
        $this->ensureSorted();
        return $this->routes;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Removes a route by its ID.
     */
    public function removeRoute(string $routeId): void
    {
        $this->routes = array_values(
            array_filter($this->routes, fn(Route $r) => $r->getId() !== $routeId)
        );
    }

    /**
     * Flushes all registered routes.
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->sorted = false;
    }

    private function ensureSorted(): void
    {
        if (!$this->sorted) {
            usort($this->routes, fn(Route $a, Route $b) => $a->getPriority() <=> $b->getPriority());
            $this->sorted = true;
        }
    }
}
