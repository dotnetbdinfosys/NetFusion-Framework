<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/ThrottleRequests.php
| Module    : Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Rate limiting & Token Bucket throttling middleware with RFC headers.
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

use NetFusion\Core\Http\RateLimiting\Limit;
use NetFusion\Core\Http\RateLimiting\RateLimiter;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * Class ThrottleRequests
 *
 * Middleware providing rate limiting and burst protection for HTTP endpoints.
 *
 * @package NetFusion\Core\Http\Middleware
 */
class ThrottleRequests implements MiddlewareInterface
{
    protected RateLimiter $limiter;
    protected int $maxAttempts;
    protected int $decayMinutes;
    protected ?string $limiterName = null;

    /**
     * @param RateLimiter|null $limiter
     * @param string|null      $guard Parameters passed via router (e.g., '60,1' or 'api')
     * @param int              $maxAttempts
     * @param int              $decayMinutes
     */
    public function __construct(
        ?RateLimiter $limiter = null,
        ?string $guard = null,
        int $maxAttempts = 60,
        int $decayMinutes = 1
    ) {
        if ($limiter !== null) {
            $this->limiter = $limiter;
        } elseif (function_exists('app') && app()->has('rate.limiter')) {
            $this->limiter = app('rate.limiter');
        } else {
            $this->limiter = new RateLimiter();
        }

        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;

        if ($guard !== null && $guard !== '') {
            if (str_contains($guard, ',')) {
                $parts = explode(',', $guard);
                $this->maxAttempts = (int) ($parts[0] ?? $maxAttempts);
                $this->decayMinutes = (int) ($parts[1] ?? $decayMinutes);
            } else {
                $this->limiterName = $guard;
            }
        }
    }

    /**
     * Handle the incoming request.
     *
     * @param Request  $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        $maxAttempts = $this->maxAttempts;
        $decaySeconds = $this->decayMinutes * 60;
        $key = null;

        if ($this->limiterName !== null && ($limiterCallback = $this->limiter->limiter($this->limiterName)) !== null) {
            $limit = $limiterCallback($request);
            if ($limit instanceof Limit) {
                $maxAttempts = $limit->maxAttempts;
                $decaySeconds = $limit->decaySeconds;
                $key = $limit->key;
            }
        }

        if ($key === null) {
            $key = $this->resolveRequestSignature($request);
        }

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildTooManyRequestsResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decaySeconds);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts),
            $this->limiter->availableIn($key)
        );
    }

    /**
     * Resolve request signature for rate limiting key.
     *
     * @param Request $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $userId = $request->getAttribute('user_id')
            ?? $request->getAttribute('jwt_user_id')
            ?? $request->getAttribute('token_user_id');

        if ($userId === null && ($user = $request->getAttribute('user')) !== null) {
            $userId = is_object($user) && isset($user->id) ? $user->id : (is_array($user) ? ($user['id'] ?? null) : null);
        }

        $identifier = $userId !== null ? 'user:' . $userId : 'ip:' . ($request->ip() ?: '127.0.0.1');
        $endpoint = $request->method() . '|' . ltrim($request->path(), '/');

        return 'throttle:' . sha1($identifier . '|' . $endpoint);
    }

    /**
     * Calculate remaining attempts for the given key.
     *
     * @param string $key
     * @param int    $maxAttempts
     * @return int
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return $this->limiter->remaining($key, $maxAttempts);
    }

    /**
     * Create 429 Too Many Requests response.
     *
     * @param string $key
     * @param int    $maxAttempts
     * @return Response
     */
    protected function buildTooManyRequestsResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);
        if ($retryAfter <= 0) {
            $retryAfter = 60;
        }

        return Response::json([
            'status'      => 'error',
            'code'        => 429,
            'message'     => 'Too Many Requests.',
            'retry_after' => $retryAfter,
        ], 429)
        ->withHeader('Retry-After', (string) $retryAfter)
        ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
        ->withHeader('X-RateLimit-Remaining', '0')
        ->withHeader('X-RateLimit-Reset', (string) (time() + $retryAfter));
    }

    /**
     * Add rate limit headers to response.
     *
     * @param Response $response
     * @param int      $maxAttempts
     * @param int      $remainingAttempts
     * @param int      $retryAfter
     * @return Response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, int $retryAfter = 0): Response
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $remainingAttempts))
            ->withHeader('X-RateLimit-Reset', (string) (time() + $retryAfter));
    }
}
