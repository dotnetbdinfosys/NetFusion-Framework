<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Routing/Router.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Central routing engine supporting verbs, route groups, resource routes, and dispatching.
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
| File      : core/Routing/Router.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Central routing engine supporting verbs, route groups, resource routes, and dispatching.
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
| File      : core/Routing/Router.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Central routing engine supporting verbs, route groups, resource routes, and dispatching.
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

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * NetFusion Router
 *
 * The central route registration and dispatching engine.
 *
 * Features:
 *  - Fluent route registration: get(), post(), put(), patch(), delete(), options(), any()
 *  - Route groups with shared prefix, middleware, and namespace
 *  - Named routes: route('users.show', ['id' => 42])
 *  - Middleware aliases & global middleware stack
 *  - Resource route registration (RESTful CRUD)
 *  - Route file loading from modules
 *
 * Usage in route files:
 *   $router->get('/users', [UserController::class, 'index'])->name('users.index');
 *   $router->group(['prefix' => 'api', 'middleware' => 'auth:api'], function($r) {
 *       $r->get('/me', [UserController::class, 'me']);
 *   });
 *
 * @package NetFusion\Core\Routing
 * @version 1.0.0
 */
class Router
{
    /**
     * The route collection storing all registered routes.
     */
    private RouteCollection $routes;

    /**
     * Global middleware applied to every route.
     *
     * @var string[]
     */
    private array $globalMiddleware = [];

    /**
     * Middleware aliases (short-names mapped to FQCN).
     *
     * @var array<string, string>
     */
    private array $middlewareAliases = [];

    /**
     * The currently active group attributes stack.
     * Each group push adds an array of [prefix, middleware, namespace].
     *
     * @var array<int, array<string, mixed>>
     */
    private array $groupStack = [];

    /**
     * Custom 404 Not Found handler.
     *
     * @var \Closure|null
     */
    private ?\Closure $notFoundHandler = null;

    /**
     * Custom 405 Method Not Allowed handler.
     *
     * @var \Closure|null
     */
    private ?\Closure $methodNotAllowedHandler = null;

    public function __construct()
    {
        $this->routes = new RouteCollection();
    }

    // =========================================================================
    //  Route Registration — HTTP Verb Methods
    // =========================================================================

    /**
     * Register a GET route.
     *
     * @param  string                             $pattern The URI pattern.
     * @param  \Closure|string|array<int,mixed>   $handler The route handler.
     * @return Route                                       The created Route (for fluent chaining).
     */
    public function get(string $pattern, \Closure|string|array $handler): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $pattern, $handler);
    }

    /**
     * Register a POST route.
     */
    public function post(string $pattern, \Closure|string|array $handler): Route
    {
        return $this->addRoute(['POST'], $pattern, $handler);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $pattern, \Closure|string|array $handler): Route
    {
        return $this->addRoute(['PUT'], $pattern, $handler);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $pattern, \Closure|string|array $handler): Route
    {
        return $this->addRoute(['PATCH'], $pattern, $handler);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $pattern, \Closure|string|array $handler): Route
    {
        return $this->addRoute(['DELETE'], $pattern, $handler);
    }

    /**
     * Register an OPTIONS route.
     */
    public function options(string $pattern, \Closure|string|array $handler): Route
    {
        return $this->addRoute(['OPTIONS'], $pattern, $handler);
    }

    /**
     * Register a route that responds to all HTTP methods.
     */
    public function any(string $pattern, \Closure|string|array $handler): Route
    {
        return $this->addRoute(
            ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            $pattern,
            $handler
        );
    }

    /**
     * Register a route that responds to multiple specific HTTP methods.
     *
     * @param  string[]                           $methods HTTP methods (uppercase).
     * @param  string                             $pattern URI pattern.
     * @param  \Closure|string|array<int,mixed>   $handler Route handler.
     * @return Route
     */
    public function match(
        array $methods,
        string $pattern,
        \Closure|string|array $handler
    ): Route {
        return $this->addRoute($methods, $pattern, $handler);
    }

    // =========================================================================
    //  RESTful Resource Routes
    // =========================================================================

    /**
     * Register a full set of RESTful resource routes for a controller.
     *
     * Generates the following routes:
     *   GET    /{resource}           → index()
     *   GET    /{resource}/create    → create()
     *   POST   /{resource}           → store()
     *   GET    /{resource}/{id}      → show()
     *   GET    /{resource}/{id}/edit → edit()
     *   PUT    /{resource}/{id}      → update()
     *   PATCH  /{resource}/{id}      → update()
     *   DELETE /{resource}/{id}      → destroy()
     *
     * @param  string          $resource   The resource name (e.g., 'users').
     * @param  string          $controller The controller FQCN or 'Controller@method' prefix.
     * @param  string[]        $only       Limit to specific actions.
     * @param  string[]        $except     Exclude specific actions.
     * @return Route[]                     The created routes.
     */
    public function resource(
        string $resource,
        string $controller,
        array  $only   = [],
        array  $except = [],
    ): array {
        $resourceActions = [
            'index'   => ['GET',           "/{$resource}",              'index'],
            'create'  => ['GET',           "/{$resource}/create",       'create'],
            'store'   => ['POST',          "/{$resource}",              'store'],
            'show'    => ['GET',           "/{$resource}/{id}",         'show'],
            'edit'    => ['GET',           "/{$resource}/{id}/edit",    'edit'],
            'update'  => ['PUT|PATCH',     "/{$resource}/{id}",         'update'],
            'destroy' => ['DELETE',        "/{$resource}/{id}",         'destroy'],
        ];

        $created = [];
        foreach ($resourceActions as $action => [$methods, $pattern, $method]) {
            // Apply only/except filters
            if (!empty($only) && !in_array($action, $only, true)) {
                continue;
            }
            if (!empty($except) && in_array($action, $except, true)) {
                continue;
            }

            $methodArray = str_contains($methods, '|') ? explode('|', $methods) : [$methods];

            $route = $this->addRoute(
                $methodArray,
                $pattern,
                $controller . '@' . $method
            )->name("{$resource}.{$action}");

            if ($action === 'update') {
                // Accept both PUT and PATCH for update
                $route->whereNumber('id');
            } elseif (in_array($action, ['show', 'edit', 'destroy'], true)) {
                $route->whereNumber('id');
            }

            $created[] = $route;
        }

        return $created;
    }

    // =========================================================================
    //  Route Groups
    // =========================================================================

    /**
     * Create a route group with shared attributes.
     *
     * Supported group attributes:
     *   'prefix'     — URI prefix prepended to all child routes.
     *   'middleware' — Middleware applied to all child routes.
     *   'namespace'  — Controller namespace prefix (for string handlers).
     *   'name'       — Route name prefix applied to all child names.
     *
     * @param array<string, mixed> $attributes Group attributes.
     * @param \Closure             $callback   Closure that registers child routes.
     *                                         Receives the Router as its argument.
     */
    public function group(array $attributes, \Closure $callback): void
    {
        // Merge with the parent group attributes (if nested)
        $merged = $this->mergeGroupAttributes($attributes);
        $this->groupStack[] = $merged;

        // Execute the callback to register child routes
        $callback($this);

        // Pop the group from the stack
        array_pop($this->groupStack);
    }

    /**
     * Create an API versioned route group (e.g. 'v1' mounts to prefix 'api/v1' with name prefix 'api.v1.').
     *
     * @param string   $version  API version identifier (e.g., 'v1', 'v2').
     * @param \Closure $callback Closure to register versioned routes.
     */
    public function apiVersion(string $version, \Closure $callback): void
    {
        $version = strtolower(ltrim($version, '/'));
        $this->group([
            'prefix' => 'api/' . $version,
            'name'   => 'api.' . $version . '.',
        ], $callback);
    }

    /**
     * Merge the given attributes with the current group stack.
     *
     * @param  array<string, mixed> $new  New group attributes.
     * @return array<string, mixed>       Merged attributes.
     */
    private function mergeGroupAttributes(array $new): array
    {
        $parent = end($this->groupStack) ?: [];

        return [
            'prefix'     => rtrim(($parent['prefix'] ?? '') . '/' . ltrim($new['prefix'] ?? '', '/'), '/'),
            'middleware' => array_merge((array) ($parent['middleware'] ?? []), (array) ($new['middleware'] ?? [])),
            'namespace'  => ($parent['namespace'] ?? '') . ($new['namespace'] ?? ''),
            'name'       => ($parent['name'] ?? '') . ($new['name'] ?? ''),
        ];
    }

    // =========================================================================
    //  Middleware
    // =========================================================================

    /**
     * Register a global middleware that applies to every route.
     *
     * @param string $middleware The middleware FQCN or alias.
     * @return $this
     */
    public function addGlobalMiddleware(string $middleware): static
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Register a short-name alias for a middleware FQCN.
     *
     * @param string $alias     Short name (e.g., 'auth').
     * @param string $className Fully-qualified class name.
     * @return $this
     */
    public function aliasMiddleware(string $alias, string $className): static
    {
        $this->middlewareAliases[$alias] = $className;
        return $this;
    }

    /**
     * Resolve a middleware alias to its FQCN.
     * Returns the input unchanged if it's not a known alias.
     *
     * @param  string $alias Middleware alias or FQCN.
     * @return string        Resolved FQCN.
     */
    public function resolveMiddleware(string $alias): string
    {
        // Support 'auth:guard' style parameters
        [$name] = explode(':', $alias, 2);
        return $this->middlewareAliases[$name] ?? $alias;
    }

    // =========================================================================
    //  Dispatching
    // =========================================================================

    /**
     * Dispatch a Request to the matching route handler.
     * Returns a Response object.
     *
     * This method:
     *   1. Matches the request to a route.
     *   2. Injects route params into the Request object.
     *   3. Resolves the handler (controller, closure, string).
     *   4. Builds the middleware pipeline for the route.
     *   5. Returns the final Response.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  callable $resolver Dependency resolver (the DI Container's make/call).
     * @return Response           The HTTP response.
     */
    public function dispatch(Request $request, callable $resolver): Response
    {
        $match = $this->routes->match($request->method(), $request->path());

        if ($match->isNotFound()) {
            return $this->handleNotFound($request);
        }

        if ($match->isMethodNotAllowed()) {
            return $this->handleMethodNotAllowed($request, $match->allowedMethods);
        }

        // Inject route params into the request
        $request->setRouteParams($match->params);

        $route = $match->route;

        // Build the merged middleware list: global + route-specific
        $middleware = array_merge($this->globalMiddleware, $route->getMiddleware());
        $middleware = array_map([$this, 'resolveMiddleware'], $middleware);

        // Build and execute the middleware pipeline
        $pipeline = new \NetFusion\Core\Http\Middleware\Pipeline($resolver);

        return $pipeline
            ->send($request)
            ->through($middleware)
            ->then(fn(Request $req) => $this->callRouteHandler($route, $req, $resolver));
    }

    /**
     * Invoke the route handler and ensure a Response is returned.
     *
     * @param  Route    $route    The matched route.
     * @param  Request  $request  The current request.
     * @param  callable $resolver The DI resolver callable.
     * @return Response
     */
    private function callRouteHandler(Route $route, Request $request, callable $resolver): Response
    {
        $handler = $route->getHandler();

        // Resolve 'Controller@method' string syntax
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $handler          = [$resolver($class), $method];
        }

        // Resolve [ControllerClass::class, 'method'] array syntax
        if (is_array($handler) && is_string($handler[0])) {
            $handler[0] = $resolver($handler[0]);
        }

        // Inject both Request and route params into the handler call
        $result = $resolver->call($handler, [
            'request' => $request,
            Request::class => $request,
        ]);

        // Ensure the result is always a Response object
        return $this->toResponse($result);
    }

    /**
     * Convert any return value to a Response object.
     *
     * @param  mixed $result The handler's return value.
     * @return Response
     */
    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return new Response((string) $result);
    }

    // =========================================================================
    //  Error Handlers
    // =========================================================================

    /**
     * Register a custom 404 Not Found handler.
     *
     * @param \Closure $handler A closure that accepts a Request and returns a Response.
     * @return $this
     */
    public function onNotFound(\Closure $handler): static
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Register a custom 405 Method Not Allowed handler.
     *
     * @param \Closure $handler A closure (Request, string[] $allowed) → Response.
     * @return $this
     */
    public function onMethodNotAllowed(\Closure $handler): static
    {
        $this->methodNotAllowedHandler = $handler;
        return $this;
    }

    /**
     * Handle a 404 Not Found response.
     */
    private function handleNotFound(Request $request): Response
    {
        if ($this->notFoundHandler !== null) {
            $result = ($this->notFoundHandler)($request);
            return $result instanceof Response ? $result : new Response((string) $result, 404);
        }

        // Default 404 response
        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json([
                'error'   => 'Not Found',
                'message' => sprintf('No route found for [%s] %s', $request->method(), $request->path()),
            ], 404);
        }

        return new Response(
            sprintf(
                '<h1>404 — Not Found</h1><p>No route matched <code>%s %s</code>.</p>',
                htmlspecialchars($request->method()),
                htmlspecialchars($request->path())
            ),
            404
        );
    }

    /**
     * Handle a 405 Method Not Allowed response.
     *
     * @param string[] $allowedMethods
     */
    private function handleMethodNotAllowed(Request $request, array $allowedMethods): Response
    {
        if ($this->methodNotAllowedHandler !== null) {
            $result = ($this->methodNotAllowedHandler)($request, $allowedMethods);
            return $result instanceof Response ? $result : new Response((string) $result, 405);
        }

        $allow = implode(', ', $allowedMethods);

        if ($request->wantsJson() || $request->isAjax()) {
            return Response::json([
                'error'   => 'Method Not Allowed',
                'allowed' => $allowedMethods,
            ], 405)->withHeader('Allow', $allow);
        }

        return (new Response(
            sprintf('<h1>405 — Method Not Allowed</h1><p>Allowed: <code>%s</code></p>', $allow),
            405
        ))->withHeader('Allow', $allow);
    }

    // =========================================================================
    //  Named Route URL Generation
    // =========================================================================

    /**
     * Generate the URL for a named route.
     *
     * @param  string                    $name    The route name.
     * @param  array<string, string|int> $params  Route parameters.
     * @param  bool                      $absolute Whether to include scheme + host.
     * @return string                             The generated URL.
     *
     * @throws \InvalidArgumentException If the named route does not exist.
     */
    public function route(string $name, array $params = [], bool $absolute = false): string
    {
        $route = $this->routes->getByName($name);

        if ($route === null) {
            throw new \InvalidArgumentException(
                sprintf('No route named [%s] found. Use ->name() when registering routes.', $name)
            );
        }

        $path = $route->generateUri($params);

        if ($absolute) {
            $scheme = 'http'; // Will be env-resolved in Phase 6
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return $scheme . '://' . $host . $path;
        }

        return $path;
    }

    // =========================================================================
    //  Route File Loading
    // =========================================================================

    /**
     * Load a route file, injecting this Router as $router.
     * Used to load global routes/web.php, routes/api.php,
     * and per-module route files.
     *
     * @param string $filePath Absolute path to the route file.
     */
    public function loadFile(string $filePath): void
    {
        if (!is_file($filePath)) {
            return;
        }

        $router = $this;
        require $filePath;
    }

    // =========================================================================
    //  Internal Helpers
    // =========================================================================

    /**
     * Create and register a Route with the current group context applied.
     *
     * @param  string[]                         $methods  HTTP methods.
     * @param  string                           $pattern  URI pattern.
     * @param  \Closure|string|array<int,mixed> $handler  Route handler.
     * @return Route                                      The created Route.
     */
    private function addRoute(
        array $methods,
        string $pattern,
        \Closure|string|array $handler,
    ): Route {
        $group      = end($this->groupStack) ?: [];
        $prefix     = $group['prefix'] ?? '';
        $mw         = (array) ($group['middleware'] ?? []);
        $namePrefix = $group['name'] ?? '';

        // Build the full pattern with group prefix
        $fullPattern = $prefix
            ? '/' . trim($prefix, '/') . '/' . ltrim($pattern, '/')
            : $pattern;

        $route = new Route($methods, $fullPattern, $handler);

        if (!empty($mw)) {
            $route->middleware($mw);
        }

        $this->routes->add($route);

        // Inject a name-registration callback so that when ->name() is called
        // on the returned Route, the RouteCollection named index is updated.
        $collection = $this->routes;
        $route->setNameCallback(function (string $name) use ($collection, $namePrefix, $route): void {
            $collection->updateNamedRoute($namePrefix . $name, $route);
        });

        return $route;
    }

    /**
     * Get the RouteCollection (useful for introspection and testing).
     *
     * @return RouteCollection
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }
}
