<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/OpenApi/Attributes/ApiEndpoint.php
| Module    : Core\OpenApi
| Namespace : NetFusion\Core\OpenApi\Attributes
| Purpose   : PHP 8.x Attribute to annotate controller methods as API endpoints for OpenAPI spec.
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
 * #[ApiEndpoint] — Controller Method Attribute
 *
 * Annotates a controller action method with OpenAPI metadata.
 * The OpenApiGenerator reads this attribute to build the spec automatically.
 *
 * Usage:
 *   #[ApiEndpoint(
 *       summary: 'List all billing plans',
 *       description: 'Returns all active SaaS subscription plans.',
 *       tags: ['Billing'],
 *       security: ['bearerAuth'],
 *       deprecated: false,
 *   )]
 *   public function index(Request $request): Response { ... }
 *
 * @package NetFusion\Core\OpenApi\Attributes
 * @version 1.0.0
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiEndpoint
{
    /**
     * @param string          $summary     Short one-line endpoint summary.
     * @param string          $description Longer description (supports Markdown).
     * @param array<string>   $tags        OpenAPI tags (used to group endpoints in Swagger UI).
     * @param array<string>   $security    Security schemes required (e.g., ['bearerAuth']).
     * @param bool            $deprecated  Whether the endpoint is deprecated.
     * @param string          $operationId Unique operation ID override (auto-generated if empty).
     */
    public function __construct(
        public readonly string $summary     = '',
        public readonly string $description = '',
        public readonly array  $tags        = [],
        public readonly array  $security    = [],
        public readonly bool   $deprecated  = false,
        public readonly string $operationId = '',
    ) {}
}
