<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/RateLimitMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Enforces per-IP/per-key API rate limiting with standard RFC headers.
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

use NetFusion\Core\Http\RateLimiter;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * NetFusion Rate Limit Middleware
 *
 * Enforces request rate limiting on API routes.
 * Adds standard RFC headers to every response:
 *   X-RateLimit-Limit     — Maximum allowed requests per window.
 *   X-RateLimit-Remaining — Requests remaining in the current window.
 *   X-RateLimit-Reset     — UNIX timestamp when the window resets.
 *   Retry-After           — (On 429 only) Seconds until retry is allowed.
 *
 * Usage:
 *   $router->aliasMiddleware('throttle', RateLimitMiddleware::class);
 *   $router->group(['middleware' => 'throttle:60,1'], ...); // 60 req/min
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class RateLimitMiddleware
{
    /**
     * @param RateLimiter $limiter Injected RateLimiter service.
     */
    public function __construct(
        protected readonly RateLimiter $limiter
    ) {}

    /**
     * Handle the incoming request.
     *
     * The middleware key is composed of the client IP + route path
     * to provide per-endpoint rate limiting.
     *
     * @param  Request  $request The current HTTP request.
     * @param  callable $next    The next middleware / handler.
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveKey($request);

        $maxAttempts  = $this->limiter->getMaxAttempts();
        $decaySeconds = $this->limiter->getDecaySeconds();

        if (!$this->limiter->attempt($key, $maxAttempts, $decaySeconds)) {
            return $this->tooManyRequestsResponse($key, $maxAttempts, $decaySeconds);
        }

        $response = $next($request);

        return $this->addRateLimitHeaders(
            $response,
            $key,
            $maxAttempts,
            $decaySeconds
        );
    }

    /**
     * Build the rate limiter key from the request.
     * Combines client IP and the request path for per-endpoint limiting.
     *
     * @param  Request $request
     * @return string
     */
    protected function resolveKey(Request $request): string
    {
        $ip   = $request->ip() ?: '0.0.0.0';
        $path = $request->path();
        return 'rate_limit:' . sha1($ip . '|' . $path);
    }

    /**
     * Add rate limit headers to a successful response.
     */
    protected function addRateLimitHeaders(
        Response $response,
        string   $key,
        int      $maxAttempts,
        int      $decaySeconds
    ): Response {
        return $response
            ->withHeader('X-RateLimit-Limit',     (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $this->limiter->remaining($key, $maxAttempts, $decaySeconds))
            ->withHeader('X-RateLimit-Reset',     (string) $this->limiter->availableAt($key, $decaySeconds));
    }

    /**
     * Build a 429 Too Many Requests response.
     */
    protected function tooManyRequestsResponse(
        string $key,
        int    $maxAttempts,
        int    $decaySeconds
    ): Response {
        $resetAt    = $this->limiter->availableAt($key, $decaySeconds);
        $retryAfter = max(0, $resetAt - time());

        return Response::json([
            'status'  => 'error',
            'code'    => 429,
            'message' => 'Too many requests. Please slow down.',
            'retry_after' => $retryAfter,
        ], 429)
        ->withHeader('X-RateLimit-Limit',     (string) $maxAttempts)
        ->withHeader('X-RateLimit-Remaining', '0')
        ->withHeader('X-RateLimit-Reset',     (string) $resetAt)
        ->withHeader('Retry-After',            (string) $retryAfter);
    }
}
