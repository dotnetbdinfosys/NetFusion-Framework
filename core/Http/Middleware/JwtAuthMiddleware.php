<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/JwtAuthMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Middleware that enforces JWT authentication on protected API routes.
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

use NetFusion\Core\Auth\JwtTokenManager;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * NetFusion JWT Authentication Middleware
 *
 * Validates the Bearer token on each request. Returns 401 Unauthorized
 * if the token is absent, invalid, expired, or revoked.
 *
 * Usage:
 *   $router->aliasMiddleware('auth:jwt', JwtAuthMiddleware::class);
 *   $router->group(['middleware' => 'auth:jwt'], function ($r) { ... });
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class JwtAuthMiddleware
{
    /**
     * @param JwtTokenManager $tokenManager Injected JWT manager.
     */
    public function __construct(
        protected readonly JwtTokenManager $tokenManager
    ) {}

    /**
     * Handle the incoming request.
     *
     * Extracts the Bearer token, validates it using JwtTokenManager,
     * and populates request attributes with the decoded JWT payload.
     *
     * @param  Request  $request The current HTTP request.
     * @param  callable $next    The next middleware / route handler.
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->unauthorized('Missing authorization token.');
        }

        $payload = $this->tokenManager->validate($token);

        if ($payload === null) {
            return $this->unauthorized('Token is invalid, expired, or revoked.');
        }

        // Inject the JWT payload into request attributes for downstream use
        $request->setAttribute('jwt_payload', $payload);
        $request->setAttribute('jwt_user_id', $payload['sub'] ?? null);
        $request->setAttribute('jwt_roles',   $payload['roles'] ?? []);
        $request->setAttribute('jwt_perms',   $payload['perms'] ?? []);

        return $next($request);
    }

    /**
     * Extract the Bearer token from the Authorization header or query string.
     */
    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // Fallback: query param (useful for Swagger UI / WebSockets)
        $token = $request->query('token');
        return is_string($token) && !empty($token) ? $token : null;
    }

    /**
     * Build a standardized 401 Unauthorized response.
     */
    protected function unauthorized(string $message): Response
    {
        return Response::json([
            'status'  => 'error',
            'code'    => 401,
            'message' => $message,
        ], 401)->withHeader('WWW-Authenticate', 'Bearer realm="NetFusion API"');
    }
}
