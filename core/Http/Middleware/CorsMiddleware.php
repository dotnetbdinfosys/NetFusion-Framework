<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/CorsMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Cross-Origin Resource Sharing (CORS) handler with preflight OPTIONS response.
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
| File      : core/Http/Middleware/CorsMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Cross-Origin Resource Sharing (CORS) handler with preflight OPTIONS response.
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
| File      : core/Http/Middleware/CorsMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Cross-Origin Resource Sharing (CORS) handler with preflight OPTIONS response.
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
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing (CORS) for all requests.
 * On a preflight OPTIONS request, it short-circuits the pipeline
 * and returns a 204 No Content response immediately.
 * On actual requests, it adds the appropriate CORS headers.
 *
 * Configured via environment variables:
 *   CORS_ALLOWED_ORIGINS  — Comma-separated list of allowed origins, or '*'
 *   CORS_ALLOWED_METHODS  — Comma-separated list of allowed methods
 *   CORS_ALLOWED_HEADERS  — Comma-separated list of allowed headers
 *   CORS_MAX_AGE          — Preflight cache duration in seconds
 *   CORS_ALLOW_CREDENTIALS — Whether to allow credentials
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $allowedOrigins     = '*',
        private readonly string $allowedMethods     = 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
        private readonly string $allowedHeaders      = 'Content-Type,Authorization,X-Requested-With',
        private readonly string $exposeHeaders       = '',
        private readonly int    $maxAge              = 0,
        private readonly bool   $allowCredentials    = false,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, callable $next): Response
    {
        // Handle preflight OPTIONS requests immediately
        if ($request->isPreflight()) {
            return $this->buildPreflightResponse($request);
        }

        // Process the request and add CORS headers to the response
        $response = $next($request);
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Build a 204 No Content preflight response.
     */
    private function buildPreflightResponse(Request $request): Response
    {
        $response = new Response('', 204);
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Add CORS headers to a response.
     */
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin') ?? '*';

        // Determine the allowed origin value
        if ($this->allowedOrigins === '*') {
            $allowOrigin = '*';
        } elseif ($this->isOriginAllowed($origin)) {
            $allowOrigin = $origin;
            $response->withHeader('Vary', 'Origin');
        } else {
            // Origin not allowed — return without CORS headers
            return $response;
        }

        $response->withHeader('Access-Control-Allow-Origin', $allowOrigin);
        $response->withHeader('Access-Control-Allow-Methods', $this->allowedMethods);
        $response->withHeader('Access-Control-Allow-Headers', $this->allowedHeaders);

        if ($this->exposeHeaders !== '') {
            $response->withHeader('Access-Control-Expose-Headers', $this->exposeHeaders);
        }

        if ($this->maxAge > 0) {
            $response->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }

        if ($this->allowCredentials) {
            $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Check if the request origin is in the allowed list.
     */
    private function isOriginAllowed(string $origin): bool
    {
        $allowed = array_map('trim', explode(',', $this->allowedOrigins));
        return in_array($origin, $allowed, true) || in_array('*', $allowed, true);
    }
}
