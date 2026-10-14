<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/OpenApi/Attributes/ApiResponseDoc.php
| Module    : Core\OpenApi
| Namespace : NetFusion\Core\OpenApi\Attributes
| Purpose   : PHP 8.x Attribute to document expected API responses for OpenAPI spec generation.
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

namespace NetFusion\Core\OpenApi\Attributes;

use Attribute;

/**
 * #[ApiResponseDoc] — Controller Method Attribute
 *
 * Documents an HTTP response that a controller action can return.
 * Multiple #[ApiResponseDoc] attributes can be stacked on a single method.
 *
 * Usage:
 *   #[ApiResponseDoc(200, description: 'Returns list of plans', schema: 'PlanResource')]
 *   #[ApiResponseDoc(401, description: 'Unauthenticated')]
 *   #[ApiResponseDoc(429, description: 'Rate limit exceeded')]
 *   public function index(Request $request): Response { ... }
 *
 * @package NetFusion\Core\OpenApi\Attributes
 * @version 1.0.0
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiResponseDoc
{
    /**
     * @param int    $statusCode  HTTP status code (200, 201, 400, 401, 404, 422, 429, 500 etc).
     * @param string $description Human-readable response description.
     * @param string $schema      Optional resource/schema class name shown in Swagger UI.
     */
    public function __construct(
        public readonly int    $statusCode  = 200,
        public readonly string $description = '',
        public readonly string $schema      = '',
    ) {}
}
