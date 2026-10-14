<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/SecurityHeadersMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Injects security headers (X-Frame-Options, X-Content-Type-Options, etc.) to harden HTTP responses.
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
| File      : core/Http/Middleware/SecurityHeadersMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Injects security headers (X-Frame-Options, X-Content-Type-Options, etc.) to harden HTTP responses.
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
| File      : core/Http/Middleware/SecurityHeadersMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Injects security headers (X-Frame-Options, X-Content-Type-Options, etc.) to harden HTTP responses.
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
 * Security Headers Middleware
 *
 * Injects essential HTTP security headers into every response to
 * protect against common web vulnerabilities (XSS, clickjacking,
 * MIME sniffing, information leakage, etc.).
 *
 * Headers added:
 *   X-Frame-Options          — Prevent clickjacking
 *   X-Content-Type-Options   — Prevent MIME sniffing
 *   X-XSS-Protection         — Legacy XSS filter hint for older browsers
 *   Referrer-Policy          — Control referrer information
 *   Permissions-Policy       — Restrict browser feature access
 *   Content-Security-Policy  — (configurable, OFF by default to not break apps)
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        // Prevent the page from being embedded in frames (clickjacking)
        $response->withHeader('X-Frame-Options', 'SAMEORIGIN');

        // Prevent browsers from MIME-sniffing the Content-Type
        $response->withHeader('X-Content-Type-Options', 'nosniff');

        // Legacy XSS protection for older browsers
        $response->withHeader('X-XSS-Protection', '1; mode=block');

        // Control how much referrer info is sent with requests
        $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict access to browser features (camera, microphone, etc.)
        $response->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Remove the X-Powered-By header if it was set elsewhere (security hardening)
        $response->withoutHeader('X-Powered-By');

        // Add NetFusion signature (can be disabled in production)
        if (env('APP_DEBUG', false)) {
            $response->withHeader('X-NetFusion-Version', \NetFusion\Core\Application::VERSION);
        }

        return $response;
    }
}
