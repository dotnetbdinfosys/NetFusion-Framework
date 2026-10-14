<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/OpenApi/OpenApiGenerator.php
| Module    : Core\OpenApi
| Namespace : NetFusion\Core\OpenApi
| Purpose   : Scans RouteCollection and PHP Attributes to generate OpenAPI 3.0 JSON specification.
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

namespace NetFusion\Core\OpenApi;

use NetFusion\Core\OpenApi\Attributes\ApiEndpoint;
use NetFusion\Core\OpenApi\Attributes\ApiParam;
use NetFusion\Core\OpenApi\Attributes\ApiResponseDoc;
use NetFusion\Core\Routing\RouteCollection;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * NetFusion OpenAPI 3.0 Generator
 *
 * Introspects the framework's RouteCollection and reads PHP 8.x Attributes
 * from controller classes to generate a standards-compliant OpenAPI 3.0.x
 * JSON specification.
 *
 * Features:
 *  - Auto-discovers paths from RouteCollection
 *  - Reads #[ApiEndpoint], #[ApiParam], #[ApiResponseDoc] Attributes
 *  - Generates path parameters from route patterns ({id}, {slug})
 *  - Configurable info block (title, version, description)
 *  - JWT bearerAuth security scheme auto-included
 *
 * @package NetFusion\Core\OpenApi
 * @version 1.0.0
 */
class OpenApiGenerator
{
    /**
     * Generated spec cache.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $cachedSpec = null;

    /**
     * @param RouteCollection      $routes    The router's route collection.
     * @param array<string,string> $info      API info: title, version, description, contact.
     * @param string               $serverUrl The base API server URL.
     */
    public function __construct(
        protected readonly RouteCollection $routes,
        protected array $info = [],
        protected string $serverUrl = '',
    ) {}

    // =========================================================================
    //  Public API
    // =========================================================================

    /**
     * Generate and return the full OpenAPI 3.0 specification array.
     *
     * @return array<string,mixed>
     */
    public function generate(): array
    {
        if ($this->cachedSpec !== null) {
            return $this->cachedSpec;
        }

        $spec = [
            'openapi' => '3.0.3',
            'info'    => $this->buildInfo(),
            'servers' => $this->buildServers(),
            'paths'   => $this->buildPaths(),
            'components' => $this->buildComponents(),
        ];

        $this->cachedSpec = $spec;
        return $spec;
    }

    /**
     * Serialize the spec to a JSON string.
     *
     * @param  bool $pretty Pretty-print the JSON.
     * @return string
     */
    public function toJson(bool $pretty = true): string
    {
        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0;
        return json_encode($this->generate(), $flags);
    }

    // =========================================================================
    //  Spec Section Builders
    // =========================================================================

    /**
     * Build the 'info' block.
     *
     * @return array<string,mixed>
     */
    protected function buildInfo(): array
    {
        return [
            'title'       => $this->info['title']       ?? 'NetFusion Framework API',
            'description' => $this->info['description'] ?? 'Auto-generated API documentation by NetFusion Framework.',
            'version'     => $this->info['version']     ?? '1.0.0',
            'contact'     => [
                'name'  => $this->info['contact_name']  ?? 'DotNetBD',
                'url'   => $this->info['contact_url']   ?? 'https://www.dotnetbd.com',
                'email' => $this->info['contact_email'] ?? 'support@dotnetbd.com',
            ],
            'license' => [
                'name' => 'MIT',
                'url'  => 'https://opensource.org/licenses/MIT',
            ],
        ];
    }

    /**
     * Build the 'servers' block.
     *
     * @return array<int,array<string,string>>
     */
    protected function buildServers(): array
    {
        return [
            [
                'url'         => $this->serverUrl ?: 'http://localhost',
                'description' => 'NetFusion Framework API Server',
            ],
        ];
    }

    /**
     * Build the 'components' block (security schemes etc.).
     *
     * @return array<string,mixed>
     */
    protected function buildComponents(): array
    {
        return [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type'         => 'http',
                    'scheme'       => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description'  => 'JWT access token. Obtain via POST /api/auth/login.',
                ],
            ],
        ];
    }

    /**
     * Build the 'paths' block from all registered routes.
     *
     * @return array<string,mixed>
     */
    protected function buildPaths(): array
    {
        $paths = [];

        foreach ($this->routes->flatten() as $route) {
            $pattern = $route->getPattern();
            $handler = $route->getHandler();

            // Only document routes with controller@method or [Class, 'method'] handlers
            [$controllerClass, $methodName] = $this->resolveHandlerParts($handler);

            if ($controllerClass === null || $methodName === null) {
                continue;
            }

            // Convert route pattern {param} to OpenAPI {param} (already compatible)
            $openApiPath = $this->routePatternToOpenApiPath($pattern);

            foreach ($route->getMethods() as $httpMethod) {
                if (in_array($httpMethod, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $operation = $this->buildOperation(
                    $controllerClass,
                    $methodName,
                    strtolower($httpMethod),
                    $pattern
                );

                $paths[$openApiPath][strtolower($httpMethod)] = $operation;
            }
        }

        ksort($paths);
        return $paths;
    }

    /**
     * Build a single operation object from controller method reflection.
     *
     * @param  string              $controllerClass
     * @param  string              $methodName
     * @param  string              $httpMethod
     * @param  string              $pattern
     * @return array<string,mixed>
     */
    protected function buildOperation(
        string $controllerClass,
        string $methodName,
        string $httpMethod,
        string $pattern,
    ): array {
        $operation = [
            'summary'     => ucfirst($httpMethod) . ' ' . $pattern,
            'description' => '',
            'tags'        => [],
            'parameters'  => [],
            'responses'   => [
                '200' => ['description' => 'Successful response'],
                '500' => ['description' => 'Internal server error'],
            ],
        ];

        try {
            $refClass  = new ReflectionClass($controllerClass);
            $refMethod = $refClass->getMethod($methodName);

            // Read #[ApiEndpoint] attribute
            $endpointAttrs = $refMethod->getAttributes(ApiEndpoint::class);
            if (!empty($endpointAttrs)) {
                /** @var ApiEndpoint $endpoint */
                $endpoint = $endpointAttrs[0]->newInstance();
                $operation['summary']     = $endpoint->summary     ?: $operation['summary'];
                $operation['description'] = $endpoint->description;
                $operation['tags']        = $endpoint->tags;
                $operation['deprecated']  = $endpoint->deprecated;

                if ($endpoint->operationId !== '') {
                    $operation['operationId'] = $endpoint->operationId;
                }

                if (!empty($endpoint->security)) {
                    $operation['security'] = array_map(
                        fn(string $s): array => [$s => []],
                        $endpoint->security
                    );
                }
            }

            // Read #[ApiParam] attributes
            $paramAttrs = $refMethod->getAttributes(ApiParam::class);
            foreach ($paramAttrs as $paramAttr) {
                /** @var ApiParam $param */
                $param = $paramAttr->newInstance();

                $paramEntry = [
                    'name'        => $param->name,
                    'in'          => $param->in,
                    'required'    => $param->required || $param->in === 'path',
                    'description' => $param->description,
                    'schema'      => ['type' => $param->type],
                ];

                if ($param->example !== null) {
                    $paramEntry['example'] = $param->example;
                }

                $operation['parameters'][] = $paramEntry;
            }

            // Read #[ApiResponseDoc] attributes
            $responseAttrs = $refMethod->getAttributes(ApiResponseDoc::class);
            if (!empty($responseAttrs)) {
                $operation['responses'] = [];
                foreach ($responseAttrs as $respAttr) {
                    /** @var ApiResponseDoc $resp */
                    $resp = $respAttr->newInstance();
                    $entry = ['description' => $resp->description ?: 'Response'];

                    if ($resp->schema !== '') {
                        $entry['content'] = [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/' . $resp->schema],
                            ],
                        ];
                    }

                    $operation['responses'][(string) $resp->statusCode] = $entry;
                }
            }

        } catch (ReflectionException) {
            // Controller class not found — skip attribute reading
        }

        // Auto-add path parameters from route pattern {param}
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]*)?\}/', $pattern, $matches);
        $existingParamNames = array_column($operation['parameters'], 'name');

        foreach ($matches[1] as $paramName) {
            if (!in_array($paramName, $existingParamNames, true)) {
                $operation['parameters'][] = [
                    'name'        => $paramName,
                    'in'          => 'path',
                    'required'    => true,
                    'description' => ucfirst($paramName) . ' identifier.',
                    'schema'      => ['type' => 'string'],
                ];
            }
        }

        return $operation;
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /**
     * Convert route pattern syntax to OpenAPI path syntax.
     *
     * Route: /api/billing/invoices/{id:\d+}
     * OpenAPI: /api/billing/invoices/{id}
     */
    protected function routePatternToOpenApiPath(string $pattern): string
    {
        // Remove regex constraints from {param:\d+} → {param}
        return preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*):[^}]+\}/', '{$1}', $pattern);
    }

    /**
     * Resolve handler to [ControllerClass, methodName] parts.
     *
     * @param  mixed $handler
     * @return array{string|null, string|null}
     */
    protected function resolveHandlerParts(mixed $handler): array
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            return [$class, $method];
        }

        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            return [$handler[0], $handler[1]];
        }

        return [null, null];
    }
}
