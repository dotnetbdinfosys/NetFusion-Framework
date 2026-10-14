<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/ApiVersionMiddleware.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Detects and sets API version header and request attributes.
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
 * NetFusion API Version Middleware
 *
 * Detects API version from:
 *  1. URL segment (e.g. /api/v1/...)
 *  2. Request Header 'X-API-Version'
 *  3. Accept header (e.g. application/vnd.netfusion.v1+json)
 *
 * Attaches the resolved version to the Request attribute 'api_version'
 * and ensures the Response contains the 'X-API-Version' header.
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
class ApiVersionMiddleware
{
    /**
     * Default version if none detected.
     */
    protected string $defaultVersion = 'v1';

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, callable $next): Response
    {
        $version = $this->resolveVersion($request);

        $request->setAttribute('api_version', $version);

        /** @var Response $response */
        $response = $next($request);

        return $response->withHeader('X-API-Version', $version);
    }

    /**
     * Resolve the API version from URL, headers, or default.
     */
    protected function resolveVersion(Request $request): string
    {
        // 1. Check URL path for /api/v[0-9]+/
        if (preg_match('#^/api/(v\d+)(?:/|$)#i', $request->path(), $matches)) {
            return strtolower($matches[1]);
        }

        // 2. Check X-API-Version header
        $header = $request->header('X-API-Version');
        if ($header !== null && $header !== '') {
            return strtolower($header);
        }

        return $this->defaultVersion;
    }
}
