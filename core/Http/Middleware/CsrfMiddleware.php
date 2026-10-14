<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/CsrfMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Verifies cryptographic CSRF tokens on state-changing requests (POST, PUT, PATCH, DELETE).
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
| File      : core/Http/Middleware/CsrfMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Verifies cryptographic CSRF tokens on state-changing requests (POST, PUT, PATCH, DELETE).
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
| File      : core/Http/Middleware/CsrfMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Verifies cryptographic CSRF tokens on state-changing requests (POST, PUT, PATCH, DELETE).
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
use NetFusion\Core\Security\Csrf;

/**
 * NetFusion CSRF Verification Middleware
 *
 * Verifies that incoming state-changing requests (POST, PUT, PATCH, DELETE)
 * contain a valid CSRF token in the request body or headers.
 *
 * Exempts:
 *  - Safe HTTP methods (GET, HEAD, OPTIONS)
 *  - Routes under /api/*
 *  - Configurable excluded route patterns
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * URI patterns that should be excluded from CSRF verification.
     *
     * @var string[]
     */
    protected array $except = [
        'api/*',
    ];

    /**
     * @param Csrf $csrf
     */
    public function __construct(
        private readonly Csrf $csrf
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, callable $next): Response
    {
        if ($this->isReading($request) || $this->inExceptArray($request)) {
            return $next($request);
        }

        $token = $this->extractToken($request);

        if (!$this->csrf->validate($token)) {
            if ($request->wantsJson() || $request->isAjax()) {
                return Response::json([
                    'error'   => 'CSRF Token Mismatch',
                    'message' => 'Your session or CSRF token is invalid or expired. Please refresh the page.',
                ], 419);
            }

            return new Response(
                '<h1>419 — Page Expired</h1><p>CSRF token mismatch. Please go back and reload the page.</p>',
                419
            );
        }

        return $next($request);
    }

    /**
     * Check if request method is read-only.
     */
    protected function isReading(Request $request): bool
    {
        return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS'], true);
    }

    /**
     * Check if the request path matches any excluded pattern.
     */
    protected function inExceptArray(Request $request): bool
    {
        $path = trim($request->path(), '/');

        foreach ($this->except as $except) {
            $pattern = trim($except, '/');
            if ($pattern === $path) {
                return true;
            }

            // Wildcard matching e.g. api/*
            if (str_ends_with($pattern, '*')) {
                $prefix = rtrim($pattern, '*');
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract token from input payload or HTTP headers.
     */
    protected function extractToken(Request $request): ?string
    {
        $token = $request->input('_token');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $headerToken = $request->header('X-CSRF-TOKEN') ?? $request->header('X-XSRF-TOKEN');
        if (is_string($headerToken) && $headerToken !== '') {
            return $headerToken;
        }

        return null;
    }
}
