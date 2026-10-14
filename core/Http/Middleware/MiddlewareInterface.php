<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/Middleware/MiddlewareInterface.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Onion-model middleware contract for handling and filtering HTTP requests and responses.
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
| File      : core/Http/Middleware/MiddlewareInterface.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Onion-model middleware contract for handling and filtering HTTP requests and responses.
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
| File      : core/Http/Middleware/MiddlewareInterface.php
| Module    : Core\Http\Middleware
| Namespace : NetFusion\Core\Http\Middleware
| Purpose   : Onion-model middleware contract for handling and filtering HTTP requests and responses.
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
 * NetFusion Middleware Interface
 *
 * All middleware must implement this interface. Each middleware receives
 * the current Request and a $next callable that represents the next layer
 * in the pipeline (onion model).
 *
 * @package NetFusion\Core\Http\Middleware
 * @version 1.0.0
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming request and return a response.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  callable $next     The next middleware layer. Signature: fn(Request) → Response.
     * @return Response           The HTTP response (from this layer or inner layers).
     */
    public function handle(Request $request, callable $next): Response;
}
