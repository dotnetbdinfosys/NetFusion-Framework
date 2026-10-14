<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/OpenApi/Attributes/ApiParam.php
| Module    : Core\OpenApi
| Namespace : NetFusion\Core\OpenApi\Attributes
| Purpose   : PHP 8.x Attribute to define a request parameter (path, query, header) for OpenAPI.
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
 * #[ApiParam] — Controller Method Attribute
 *
 * Describes a path, query, or header parameter on an API endpoint.
 * Multiple #[ApiParam] attributes can be stacked on a single method.
 *
 * Usage:
 *   #[ApiParam(name: 'id', in: 'path', type: 'integer', required: true, description: 'Plan ID')]
 *   #[ApiParam(name: 'page', in: 'query', type: 'integer', required: false, description: 'Page number')]
 *   public function show(Request $request): Response { ... }
 *
 * @package NetFusion\Core\OpenApi\Attributes
 * @version 1.0.0
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiParam
{
    /**
     * @param string $name        Parameter name.
     * @param string $in          Location: 'path' | 'query' | 'header' | 'cookie'.
     * @param string $type        Data type: 'string' | 'integer' | 'boolean' | 'number' | 'array' | 'object'.
     * @param bool   $required    Whether the parameter is required.
     * @param string $description Human-readable description.
     * @param mixed  $example     Example value shown in Swagger UI.
     * @param mixed  $default     Default value.
     */
    public function __construct(
        public readonly string $name        = '',
        public readonly string $in          = 'query',
        public readonly string $type        = 'string',
        public readonly bool   $required    = false,
        public readonly string $description = '',
        public readonly mixed  $example     = null,
        public readonly mixed  $default     = null,
    ) {}
}
