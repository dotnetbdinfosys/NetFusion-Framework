<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/EnsureFeatureActive.php
| Module    : Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Middleware guarding routes behind dynamic Feature Flags.
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

use NetFusion\Core\Features\FeatureManager;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * Class EnsureFeatureActive
 *
 * Middleware ensuring a required feature flag is active for the current request scope.
 *
 * @package NetFusion\Core\Http\Middleware
 */
class EnsureFeatureActive implements MiddlewareInterface
{
    protected FeatureManager $features;

    /**
     * @param FeatureManager|null $features
     * @param string|null         $guard Feature name passed via route definition (e.g. 'feature:beta-v2')
     */
    public function __construct(
        ?FeatureManager $features = null,
        protected ?string $guard = null
    ) {
        if ($features !== null) {
            $this->features = $features;
        } elseif (function_exists('app') && app()->has('features')) {
            $this->features = app('features');
        } else {
            $this->features = new FeatureManager();
        }
    }

    /**
     * Handle incoming request.
     *
     * @param Request  $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        if ($this->guard === null || $this->guard === '') {
            return $next($request);
        }

        $scope = $request->getAttribute('user')
            ?? $request->getAttribute('user_id')
            ?? $request->getAttribute('token_user_id')
            ?? $request->ip();

        if (! $this->features->active($this->guard, $scope)) {
            return Response::json([
                'status'  => 'error',
                'code'    => 404,
                'message' => sprintf('Feature [%s] is not enabled.', $this->guard),
            ], 404);
        }

        return $next($request);
    }
}
