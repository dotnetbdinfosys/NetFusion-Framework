<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/Authenticate.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Authentication enforcement middleware verifying user session or API token credentials.
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

use NetFusion\Core\Auth\AuthManager;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * NetFusion Authentication Middleware
 *
 * Enforces authentication on protected routes across specified guards.
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class Authenticate implements MiddlewareInterface
{
    /**
     * @param AuthManager $auth
     * @param string|null $guard
     */
    public function __construct(
        protected readonly AuthManager $auth,
        protected readonly ?string     $guard = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, callable $next): Response
    {
        $guard = $this->auth->guard($this->guard);

        if ($guard->guest()) {
            if ($request->wantsJson() || $request->isAjax() || $this->guard === 'api') {
                return Response::json([
                    'error'   => 'Unauthenticated',
                    'message' => 'Authentication is required to access this resource.',
                ], 401);
            }

            return Response::redirect('/login');
        }

        // Attach authenticated user to request attributes
        $request->setAttribute('user', $guard->user());

        return $next($request);
    }
}
