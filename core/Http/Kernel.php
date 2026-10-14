<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Kernel.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : HTTP Kernel orchestrating global middleware pipeline, route dispatching, and error handling.
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
| File      : core/Http/Kernel.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : HTTP Kernel orchestrating global middleware pipeline, route dispatching, and error handling.
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
| File      : core/Http/Kernel.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : HTTP Kernel orchestrating global middleware pipeline, route dispatching, and error handling.
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

namespace NetFusion\Core\Http;

use NetFusion\Core\Application;
use NetFusion\Core\Routing\Router;
use NetFusion\Core\Http\Middleware\Pipeline;

/**
 * NetFusion HTTP Kernel
 *
 * The orchestrator of the entire HTTP request lifecycle:
 *
 *  1. Receives the incoming Request (from public/index.php).
 *  2. Bootstraps the Router (loads global + module route files).
 *  3. Dispatches the request through the middleware pipeline → router.
 *  4. Returns the Response to public/index.php for output.
 *  5. Handles uncaught exceptions and converts them to error responses.
 *
 * The Kernel is registered as a singleton in the DI Container
 * by the Application class. Route files are loaded lazily on the
 * first request (not during construction) to keep boot time minimal.
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class Kernel
{
    /**
     * Global middleware stack applied to EVERY request (before routing).
     *
     * @var string[]
     */
    protected array $globalMiddleware = [
        \NetFusion\Core\Http\Middleware\SecurityHeadersMiddleware::class,
        \NetFusion\Core\Http\Middleware\CorsMiddleware::class,
    ];

    /**
     * Middleware group aliases.
     * 'web' group is for browser routes. 'api' group for JSON API routes.
     *
     * @var array<string, string[]>
     */
    protected array $middlewareGroups = [
        'web' => [
            // CsrfMiddleware::class,  — Added in Phase 4
            // SessionMiddleware::class, — Added in Phase 6
        ],
        'api' => [
            // ThrottleMiddleware::class, — Added in Phase 4
        ],
    ];

    /**
     * Short-name aliases for individual middleware.
     *
     * @var array<string, string>
     */
    protected array $middlewareAliases = [
        'cors'     => \NetFusion\Core\Http\Middleware\CorsMiddleware::class,
        'security' => \NetFusion\Core\Http\Middleware\SecurityHeadersMiddleware::class,
        'csrf'     => \NetFusion\Core\Http\Middleware\CsrfMiddleware::class,
        'tenant'     => \NetFusion\Core\Http\Middleware\TenantIdentificationMiddleware::class,
        'auth'       => \NetFusion\Core\Http\Middleware\Authenticate::class,
        'throttle'   => \NetFusion\Core\Http\Middleware\ThrottleRequests::class,
        'auth.token' => \NetFusion\Core\Auth\Tokens\AuthenticateWithApiToken::class,
        'trace'      => \NetFusion\Core\Http\Middleware\TraceRequestMiddleware::class,
        'feature'    => \NetFusion\Core\Http\Middleware\EnsureFeatureActive::class,
    ];

    /**
     * Whether routes have been loaded.
     */
    private bool $routesLoaded = false;

    /**
     * @param Application $app    The application DI container.
     * @param Router      $router The router instance.
     */
    public function __construct(
        private readonly Application $app,
        private readonly Router      $router,
    ) {}

    // =========================================================================
    //  Request Handling
    // =========================================================================

    /**
     * Handle an incoming HTTP Request and return a Response.
     * This is the primary entry point called by public/index.php.
     *
     * @param  Request  $request The incoming HTTP request.
     * @return Response          The HTTP response to send.
     */
    public function handle(Request $request): Response
    {
        try {
            // Ensure the Application is booted (discovers modules, boots providers)
            $this->app->boot();

            // Lazy-load route files on first request
            $this->bootRoutes();

            $resolver = $this->buildResolver();

            // Pass through Kernel's global middleware pipeline to the router dispatch
            $pipeline = new Pipeline($resolver);

            return $pipeline
                ->send($request)
                ->through($this->globalMiddleware)
                ->then(fn(Request $req) => $this->router->dispatch($req, $resolver));

        } catch (\Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * Terminate the request lifecycle (called after send()).
     * Can be used for cleanup, analytics, or queue flushing.
     *
     * @param Request  $request  The original request.
     * @param Response $response The response that was sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Performance tracking / termination hooks will be added in Phase 6
    }

    // =========================================================================
    //  Route Bootstrapping
    // =========================================================================

    /**
     * Register middleware aliases and load route files.
     * This runs once per request, on the first handle() call.
     */
    protected function bootRoutes(): void
    {
        if ($this->routesLoaded) {
            return;
        }

        // 1. Register middleware aliases on the Router
        foreach ($this->middlewareAliases as $alias => $class) {
            $this->router->aliasMiddleware($alias, $class);
        }

        // 2. Load global web routes
        $webRoutes = $this->app->basePath('routes/web.php');
        $this->router->loadFile($webRoutes);

        // 3. Load global API routes under /api prefix
        $apiRoutes = $this->app->basePath('routes/api.php');
        if (is_file($apiRoutes)) {
            $this->router->group(['prefix' => 'api'], function (Router $r) use ($apiRoutes) {
                $r->loadFile($apiRoutes);
            });
        }

        // 4. Auto-discover and mount module routes from enabled HMVC modules
        $this->app->make(\NetFusion\Core\Modules\ModuleLoader::class)->loadModuleRoutes($this->router);

        $this->routesLoaded = true;
    }

    // =========================================================================
    //  DI Resolver
    // =========================================================================

    /**
     * Build a resolver callable for use by the Router and Pipeline.
     *
     * The resolver is a simple closure wrapping the DI Container's make()
     * and call() methods. This decouples the Router from the Container.
     *
     * @return object  An object with make() and call() methods.
     */
    protected function buildResolver(): object
    {
        $app = $this->app;

        return new class($app) {
            public function __construct(private Application $app) {}

            /** Resolve/build a class from the DI container. */
            public function __invoke(string $class, array $params = []): mixed
            {
                return $this->app->make($class, $params);
            }

            /** Resolve a class. Alias for __invoke(). */
            public function make(string $class, array $params = []): mixed
            {
                return $this->app->make($class, $params);
            }

            /** Call a callable with auto-injected dependencies. */
            public function call(callable|string $callable, array $overrides = []): mixed
            {
                return $this->app->call($callable, $overrides);
            }
        };
    }

    // =========================================================================
    //  Exception Handling
    // =========================================================================

    /**
     * Convert an uncaught Throwable into an appropriate HTTP error Response.
     *
     * @param  Request    $request   The request that triggered the exception.
     * @param  \Throwable $exception The caught exception or error.
     * @return Response              A formatted error response.
     */
    protected function handleException(Request $request, \Throwable $exception): Response
    {
        // Log the error (Phase 3 will add PSR-3 Logger support)
        $this->logException($exception);

        $isDebug = $this->app->isDebug();

        // Determine the HTTP status code from the exception code (if valid)
        $statusCode = $this->resolveStatusCode($exception);

        // JSON responses for API/AJAX requests
        if ($request->wantsJson() || $request->isAjax()) {
            $data = [
                'error'   => $this->getExceptionTitle($statusCode),
                'message' => $exception->getMessage() ?: 'An unexpected error occurred.',
            ];

            if ($isDebug) {
                $data['debug'] = [
                    'exception' => get_class($exception),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine(),
                    'trace'     => explode("\n", $exception->getTraceAsString()),
                ];
            }

            return Response::json($data, $statusCode);
        }

        // HTML responses for browser requests
        if ($isDebug) {
            return new Response(
                $this->renderDebugPage($exception, $statusCode),
                $statusCode
            );
        }

        return new Response(
            $this->renderProductionErrorPage($statusCode, $exception->getMessage()),
            $statusCode
        );
    }

    /**
     * Resolve an HTTP status code from an exception.
     * Uses the exception's code if it's a valid HTTP status code (400-599).
     */
    private function resolveStatusCode(\Throwable $exception): int
    {
        $code = $exception->getCode();
        if (is_int($code) && $code >= 400 && $code < 600) {
            return $code;
        }
        return 500;
    }

    /**
     * Get a human-readable title for an HTTP error code.
     */
    private function getExceptionTitle(int $code): string
    {
        return Response::STATUS_PHRASES[$code] ?? 'Server Error';
    }

    /**
     * Render a detailed debug error page for development.
     */
    private function renderDebugPage(\Throwable $exception, int $statusCode): string
    {
        $class          = get_class($exception);
        $message        = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file           = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
        $line           = $exception->getLine();
        $trace          = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        $version        = \NetFusion\Core\Application::VERSION;
        $serverSoftware = htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? ('PHP ' . PHP_VERSION), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error {$statusCode} — NetFusion</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
                .header { background: #ef4444; padding: 24px 40px; }
                .header h1 { font-size: 28px; font-weight: 700; color: #fff; }
                .header p { font-size: 14px; color: #fecaca; margin-top: 4px; }
                .badge { background: #fff; color: #ef4444; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; margin-right: 8px; }
                .container { max-width: 1100px; margin: 0 auto; padding: 32px 40px; }
                .message { background: #1e293b; border-left: 4px solid #ef4444; border-radius: 6px; padding: 20px 24px; margin-bottom: 24px; }
                .message code { font-family: 'Cascadia Code', 'Fira Code', monospace; font-size: 16px; color: #fca5a5; }
                .meta { display: flex; gap: 24px; margin-bottom: 24px; flex-wrap: wrap; }
                .meta-item { background: #1e293b; border-radius: 6px; padding: 16px 20px; flex: 1; min-width: 200px; }
                .meta-item label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; }
                .meta-item span { display: block; color: #e2e8f0; font-size: 14px; margin-top: 4px; font-family: monospace; }
                .trace-box { background: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 20px; overflow-x: auto; }
                .trace-box h3 { font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 12px; text-transform: uppercase; }
                .trace-box pre { font-family: 'Cascadia Code', 'Fira Code', monospace; font-size: 12px; line-height: 1.7; color: #94a3b8; white-space: pre-wrap; }
                .footer { text-align: center; color: #475569; font-size: 12px; margin-top: 32px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><span class="badge">{$statusCode}</span>Uncaught Exception</h1>
                <p>NetFusion Framework v{$version} — Debug Mode</p>
            </div>
            <div class="container">
                <div class="message">
                    <code>{$class}: {$message}</code>
                </div>
                <div class="meta">
                    <div class="meta-item">
                        <label>File</label>
                        <span>{$file}</span>
                    </div>
                    <div class="meta-item">
                        <label>Line</label>
                        <span>{$line}</span>
                    </div>
                    <div class="meta-item">
                        <label>PHP Version</label>
                        <span>{$serverSoftware}</span>
                    </div>
                </div>
                <div class="trace-box">
                    <h3>Stack Trace</h3>
                    <pre>{$trace}</pre>
                </div>
                <div class="footer">NetFusion Framework v{$version} | Disable APP_DEBUG=false in production</div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Render a clean, user-friendly error page for production.
     */
    private function renderProductionErrorPage(int $statusCode, string $message = ''): string
    {
        $phrase = Response::STATUS_PHRASES[$statusCode] ?? 'Error';
        $safeMsg = $statusCode < 500 && $message
            ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            : 'An unexpected error occurred. Please try again later.';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$statusCode} {$phrase}</title>
            <style>
                body { font-family: system-ui, sans-serif; background: #f8fafc; color: #1e293b;
                       display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                .box { text-align: center; padding: 48px; }
                h1 { font-size: 72px; font-weight: 800; color: #e2e8f0; margin: 0; }
                h2 { font-size: 24px; font-weight: 600; color: #1e293b; margin: 12px 0 8px; }
                p { color: #64748b; font-size: 16px; }
                a { color: #3b82f6; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>{$statusCode}</h1>
                <h2>{$phrase}</h2>
                <p>{$safeMsg}</p>
                <p><a href="/">← Back to Home</a></p>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Log an exception to the error log (PSR-3 Logger will replace this in Phase 6).
     */
    private function logException(\Throwable $exception): void
    {
        $message = sprintf(
            '[%s] %s: %s in %s:%d',
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        $logPath = $this->app->storagePath('logs/netfusion.log');
        @error_log($message . PHP_EOL, 3, $logPath);
    }
}
