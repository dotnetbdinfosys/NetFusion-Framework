<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/Pipeline.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : PSR-15-inspired pipeline executing layered middleware stack via array_reduce.
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
| File      : core/Http/Middleware/Pipeline.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : PSR-15-inspired pipeline executing layered middleware stack via array_reduce.
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
| File      : core/Http/Middleware/Pipeline.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : PSR-15-inspired pipeline executing layered middleware stack via array_reduce.
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

namespace NetFusion\Core\Http\Middleware;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * NetFusion Middleware Pipeline
 *
 * Executes a stack of middleware in an "onion" model:
 * each layer wraps the next, and the innermost layer is the
 * final route handler (destination).
 *
 *  Client Request
 *    → Middleware 1 (before)
 *      → Middleware 2 (before)
 *        → Middleware 3 (before)
 *          → Route Handler (destination)
 *        ← Middleware 3 (after)
 *      ← Middleware 2 (after)
 *    ← Middleware 1 (after)
 *  Response to Client
 *
 * Each middleware class must implement MiddlewareInterface.
 * The pipeline resolves middleware via the DI container,
 * enabling full constructor injection in middleware.
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class Pipeline
{
    /**
     * The request being passed through the pipeline.
     */
    private Request $request;

    /**
     * The middleware stack (FQCNs or aliases).
     *
     * @var string[]
     */
    private array $middleware = [];

    /**
     * The DI resolver callable.
     * Accepts a class name and returns an instance.
     * In practice, this is: fn(string $class) => $app->make($class)
     */
    private mixed $resolver;

    /**
     * @param callable $resolver The DI resolver (e.g., [$container, 'make']).
     */
    public function __construct(callable $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Set the request to send through the pipeline.
     *
     * @param  Request $request The incoming HTTP request.
     * @return $this
     */
    public function send(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Set the middleware layers to pass the request through.
     *
     * @param  string[] $middleware Ordered array of middleware FQCNs or aliases.
     * @return $this
     */
    public function through(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Run the pipeline and execute the final destination callable.
     *
     * The destination is the innermost callable — typically the route handler
     * wrapped in a closure by the Router.
     *
     * @param  callable $destination fn(Request) → Response  — The final handler.
     * @return Response
     */
    public function then(callable $destination): Response
    {
        // Build the pipeline as a nested callable chain (right-to-left folding)
        // The rightmost middleware in the array is executed first "inside" (closest to handler)
        // while the leftmost is executed first "outside" (first to receive the request).
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->request);
    }

    /**
     * Create a closure that wraps a middleware around the next layer.
     *
     * @return \Closure Returns a reducer function for array_reduce.
     */
    private function carry(): \Closure
    {
        return function (callable $next, string $middlewareClass): \Closure {
            return function (Request $request) use ($next, $middlewareClass): Response {
                // Resolve the middleware via DI (allows constructor injection)
                $middleware = $this->resolveMiddleware($middlewareClass);

                // Execute the middleware's handle() method
                $response = $middleware->handle($request, $next);

                // Ensure the response is always a Response object
                if (!($response instanceof Response)) {
                    throw new \UnexpectedValueException(
                        sprintf(
                            'Middleware [%s] must return a [%s] instance, got [%s].',
                            $middlewareClass,
                            Response::class,
                            get_debug_type($response)
                        )
                    );
                }

                return $response;
            };
        };
    }

    /**
     * Wrap the final destination handler in a closure that matches
     * the pipeline's expected signature fn(Request) → Response.
     *
     * @param  callable $destination The final route handler callable.
     * @return \Closure
     */
    private function prepareDestination(callable $destination): \Closure
    {
        return function (Request $request) use ($destination): Response {
            $response = $destination($request);

            if (!($response instanceof Response)) {
                throw new \UnexpectedValueException(
                    sprintf(
                        'Route handler must return a [%s] instance, got [%s].',
                        Response::class,
                        get_debug_type($response)
                    )
                );
            }

            return $response;
        };
    }

    /**
     * Resolve a middleware FQCN into a MiddlewareInterface instance.
     * Uses the DI container so middleware can use constructor injection.
     *
     * Supports middleware with parameters: 'auth:api' will pass 'api'
     * as a constructor argument to the Auth middleware.
     *
     * @param  string             $middlewareClass FQCN or 'FQCN:param' string.
     * @return MiddlewareInterface
     *
     * @throws \RuntimeException If the class doesn't implement MiddlewareInterface.
     */
    private function resolveMiddleware(string $middlewareClass): MiddlewareInterface
    {
        // Support 'ClassName:parameter' syntax (e.g., 'auth:web')
        $params = [];
        if (str_contains($middlewareClass, ':')) {
            [$middlewareClass, $paramStr] = explode(':', $middlewareClass, 2);
            $params = ['guard' => $paramStr]; // Convention: first param = 'guard'
        }

        /** @var MiddlewareInterface $instance */
        $instance = ($this->resolver)($middlewareClass, $params);

        if (!($instance instanceof MiddlewareInterface)) {
            throw new \RuntimeException(
                sprintf(
                    'Middleware [%s] must implement [%s].',
                    $middlewareClass,
                    MiddlewareInterface::class
                )
            );
        }

        return $instance;
    }
}
