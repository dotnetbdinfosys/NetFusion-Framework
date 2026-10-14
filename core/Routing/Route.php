<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Routing/Route.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Route entity with regex pattern compilation, param extraction, constraints, and URI generation.
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
| File      : core/Routing/Route.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Route entity with regex pattern compilation, param extraction, constraints, and URI generation.
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
| File      : core/Routing/Route.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Route entity with regex pattern compilation, param extraction, constraints, and URI generation.
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
 * NetFusion Route
 *
 * Represents a single registered route in the framework. Encapsulates:
 *  - HTTP method(s)
 *  - URI pattern (with {param} placeholders)
 *  - Handler (Closure, 'Controller@method', or [Controller::class, 'method'])
 *  - Middleware stack
 *  - Route name (for URL generation)
 *  - Prefix (from route groups)
 *  - Compiled regex pattern for matching
 *
 * @package NetFusion\Core\Routing
 * @version 1.0.0
 */
class Route
{
    /**
     * The HTTP methods this route responds to.
     *
     * @var string[]
     */
    private array $methods;

    /**
     * The URI pattern (e.g., "/users/{id}/edit").
     */
    private string $pattern;

    /**
     * The route handler (Closure | string 'Controller@method' | array).
     *
     * @var \Closure|string|array<int, mixed>
     */
    private \Closure|string|array $handler;

    /**
     * Middleware stack for this specific route.
     *
     * @var string[]
     */
    private array $middleware = [];

    /**
     * Named route identifier.
     */
    private ?string $name = null;

    /**
     * Per-route parameter constraints (regex patterns).
     *
     * @var array<string, string>
     */
    private array $wheres = [];

    /**
     * Compiled regex for matching the URI pattern.
     */
    private ?string $compiledRegex = null;

    /**
     * Extracted parameter names from the pattern (in order of appearance).
     *
     * @var string[]
     */
    private array $parameterNames = [];

    /**
     * A callback invoked when ->name() is called, allowing the Router to
     * update the RouteCollection's named index in real-time.
     */
    private ?\Closure $nameCallback = null;

    /**
     * @param string[]                          $methods  HTTP methods (uppercase).
     * @param string                            $pattern  URI pattern with {param} placeholders.
     * @param \Closure|string|array<int,mixed>  $handler  Route handler.
     */
    public function __construct(
        array             $methods,
        string            $pattern,
        \Closure|string|array $handler,
    ) {
        $this->methods = array_map('strtoupper', $methods);
        $this->pattern = '/' . trim($pattern, '/');
        $this->handler = $handler;
    }

    // =========================================================================
    //  Fluent Configuration
    // =========================================================================

    /**
     * Assign a name to this route for URL generation.
     *
     * @param  string $name The route name (e.g., 'users.show').
     * @return $this
     */
    public function name(string $name): static
    {
        $this->name = $name;

        // Notify the Router so it can update the RouteCollection named index
        if ($this->nameCallback !== null) {
            ($this->nameCallback)($name);
        }

        return $this;
    }

    /**
     * Set the callback to invoke when ->name() is called.
     * Called internally by the Router on every registered route.
     *
     * @param \Closure $callback fn(string $name): void
     */
    public function setNameCallback(\Closure $callback): void
    {
        $this->nameCallback = $callback;
    }

    /**
     * Assign one or more middleware to this route.
     *
     * @param  string|string[] $middleware Middleware class name(s) or alias(es).
     * @return $this
     */
    public function middleware(string|array $middleware): static
    {
        $this->middleware = array_merge(
            $this->middleware,
            (array) $middleware
        );
        return $this;
    }

    /**
     * Add a parameter constraint (regex pattern for a route {param}).
     *
     * @param  string $param   Parameter name (without curly braces).
     * @param  string $pattern Regex pattern (without delimiters/anchors).
     * @return $this
     *
     * @example $route->where('id', '[0-9]+')->where('slug', '[a-z0-9\-]+');
     */
    public function where(string $param, string $pattern): static
    {
        $this->wheres[$param] = $pattern;
        // Recompile since constraints changed
        $this->compiledRegex  = null;
        return $this;
    }

    /**
     * Shorthand: constrain {id} to digits only.
     *
     * @return $this
     */
    public function whereNumber(string ...$params): static
    {
        foreach ($params as $param) {
            $this->where($param, '[0-9]+');
        }
        return $this;
    }

    /**
     * Shorthand: constrain a param to alpha-numeric with dashes (slug).
     *
     * @return $this
     */
    public function whereSlug(string ...$params): static
    {
        foreach ($params as $param) {
            $this->where($param, '[a-z0-9\-]+');
        }
        return $this;
    }

    /**
     * Shorthand: constrain a param to UUID format.
     *
     * @return $this
     */
    public function whereUuid(string ...$params): static
    {
        foreach ($params as $param) {
            $this->where(
                $param,
                '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
            );
        }
        return $this;
    }

    /**
     * Add a route prefix (used internally by route groups).
     *
     * @param  string $prefix URI prefix to prepend.
     * @return $this
     */
    public function prefix(string $prefix): static
    {
        $this->pattern       = '/' . trim($prefix, '/') . $this->pattern;
        $this->compiledRegex = null;
        return $this;
    }

    // =========================================================================
    //  Matching
    // =========================================================================

    /**
     * Determine if this route matches the given HTTP method.
     *
     * @param  string $method The HTTP method to check (uppercase).
     * @return bool
     */
    public function matchesMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods, true);
    }

    /**
     * Attempt to match the given URI path against this route's pattern.
     *
     * @param  string $path The request URI path (without query string).
     * @return array<string, string>|false
     *   Returns an associative array of extracted parameters on match,
     *   or false if the URI does not match.
     */
    public function match(string $path): array|false
    {
        $regex = $this->compile();

        if (!preg_match($regex, $path, $matches)) {
            return false;
        }

        // Extract only named captures (ignore numeric indices)
        $params = [];
        foreach ($this->parameterNames as $name) {
            if (isset($matches[$name]) && $matches[$name] !== '') {
                $params[$name] = rawurldecode($matches[$name]);
            }
        }

        return $params;
    }

    /**
     * Compile the URI pattern into a regex string.
     * Results are cached for performance.
     *
     * Pattern syntax:
     *   {param}   — Matches any character except '/' (default: [^/]+)
     *   {param?}  — Optional parameter
     *
     * @return string The full anchored regex (with delimiters).
     */
    public function compile(): string
    {
        if ($this->compiledRegex !== null) {
            return $this->compiledRegex;
        }

        $this->parameterNames = [];

        $pattern = $this->pattern;

        // Replace optional params {param?} FIRST
        // The preceding '/' is consumed into the optional group so it doesn't
        // produce a double-slash in the compiled regex.
        $pattern = preg_replace_callback(
            '/\\/\\{([a-zA-Z_][a-zA-Z0-9_]*)\\?\\}/',
            function (array $m): string {
                $name = $m[1];
                $this->parameterNames[] = $name;
                $constraint = $this->wheres[$name] ?? '[^/]+';
                return '(?:/(?P<' . $name . '>' . $constraint . '))?';
            },
            $pattern
        );

        // Replace required params {param}
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function (array $m): string {
                $name = $m[1];
                $this->parameterNames[] = $name;
                $constraint = $this->wheres[$name] ?? '[^/]+';
                return '(?P<' . $name . '>' . $constraint . ')';
            },
            $pattern
        );

        // Escape forward slashes for regex delimiter
        $pattern = str_replace('/', '\/', $pattern);

        $this->compiledRegex = '/^' . $pattern . '\/?$/u';

        return $this->compiledRegex;
    }

    // =========================================================================
    //  URL Generation
    // =========================================================================

    /**
     * Generate the URI for this route with the given parameter values.
     *
     * @param  array<string, string|int> $params Named parameters to substitute.
     * @return string                            The generated URI path.
     *
     * @throws \InvalidArgumentException If a required parameter is missing.
     */
    public function generateUri(array $params = []): string
    {
        $uri = $this->pattern;

        // Replace required parameters
        $uri = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function (array $m) use ($params): string {
                $name = $m[1];
                if (!isset($params[$name])) {
                    throw new \InvalidArgumentException(
                        sprintf('Missing required route parameter [%s].', $name)
                    );
                }
                return rawurlencode((string) $params[$name]);
            },
            $uri
        );

        // Replace optional parameters (strip if not provided)
        $uri = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/',
            function (array $m) use ($params): string {
                $name = $m[1];
                if (isset($params[$name])) {
                    return '/' . rawurlencode((string) $params[$name]);
                }
                return '';
            },
            $uri
        );

        return $uri;
    }

    // =========================================================================
    //  Accessors
    // =========================================================================

    /**
     * Get the HTTP methods for this route.
     *
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get the URI pattern.
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get the route handler.
     *
     * @return \Closure|string|array<int, mixed>
     */
    public function getHandler(): \Closure|string|array
    {
        return $this->handler;
    }

    /**
     * Get the middleware stack.
     *
     * @return string[]
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get the route name, or null if unnamed.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the parameter names extracted during compilation.
     *
     * @return string[]
     */
    public function getParameterNames(): array
    {
        $this->compile();
        return $this->parameterNames;
    }
}
