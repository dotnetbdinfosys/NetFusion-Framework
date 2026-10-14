<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/Tokens/AuthenticateWithApiToken.php
| Module    : Auth\Tokens
| Namespace : NetFusion\Core\Auth\Tokens
| Purpose   : Middleware authenticating requests via Personal Access Token Bearer credentials.
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

namespace NetFusion\Core\Auth\Tokens;

use NetFusion\Core\Http\Middleware\MiddlewareInterface;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * Class AuthenticateWithApiToken
 *
 * Enforces API token authentication and optional ability/scope verification.
 *
 * @package NetFusion\Core\Auth\Tokens
 */
class AuthenticateWithApiToken implements MiddlewareInterface
{
    protected TokenManager $tokenManager;

    /**
     * @param TokenManager|null $tokenManager
     * @param string|null       $guard Required scope / ability passed from route definition
     */
    public function __construct(
        ?TokenManager $tokenManager = null,
        protected ?string $guard = null
    ) {
        if ($tokenManager !== null) {
            $this->tokenManager = $tokenManager;
        } elseif (function_exists('app') && app()->has('token.manager')) {
            $this->tokenManager = app('token.manager');
        } else {
            $this->tokenManager = new TokenManager();
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
        $plainToken = $this->extractToken($request);

        if ($plainToken === null) {
            return $this->unauthorized('Missing authorization token.');
        }

        $token = $this->tokenManager->findToken($plainToken);

        if ($token === null) {
            return $this->unauthorized('Invalid, expired, or revoked API token.');
        }

        // Verify ability/scope requirement if specified in route middleware
        if ($this->guard !== null && $this->guard !== '' && $token->cant($this->guard)) {
            return $this->forbidden(
                sprintf('Token does not have the required ability [%s].', $this->guard)
            );
        }

        // Resolve tokenable entity
        $tokenableType = (string) $token->getAttribute('tokenable_type');
        $tokenableId   = $token->getAttribute('tokenable_id');
        $user = null;

        if (class_exists($tokenableType) && method_exists($tokenableType, 'find')) {
            $user = $tokenableType::find($tokenableId);
            if ($user !== null && method_exists($user, 'withAccessToken')) {
                $user->withAccessToken($token);
            }
        }

        // Set request attributes
        $request->setAttribute('token', $token);
        $request->setAttribute('user', $user);
        $request->setAttribute('tokenable', $user);
        $request->setAttribute('token_id', $token->getKey());
        $request->setAttribute('token_user_id', $tokenableId);
        $request->setAttribute('token_abilities', $token->getAbilities());

        return $next($request);
    }

    /**
     * Extract token from Authorization header or query parameter.
     */
    protected function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization', '');

        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        $queryToken = $request->query('api_token') ?? $request->query('token');
        if (is_string($queryToken) && $queryToken !== '') {
            return $queryToken;
        }

        return null;
    }

    /**
     * Build 401 Unauthorized response.
     */
    protected function unauthorized(string $message): Response
    {
        return Response::json([
            'status'  => 'error',
            'code'    => 401,
            'message' => $message,
        ], 401)->withHeader('WWW-Authenticate', 'Bearer realm="NetFusion API Token"');
    }

    /**
     * Build 403 Forbidden response.
     */
    protected function forbidden(string $message): Response
    {
        return Response::json([
            'status'  => 'error',
            'code'    => 403,
            'message' => $message,
        ], 403);
    }
}
