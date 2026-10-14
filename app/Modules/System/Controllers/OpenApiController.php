<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/System/Controllers/OpenApiController.php
| Module    : Modules\System
| Namespace : NetFusion\Modules\System\Controllers
| Purpose   : Serves the auto-generated OpenAPI 3.0 JSON spec and embedded Swagger UI.
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

namespace NetFusion\Modules\System\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\OpenApi\OpenApiGenerator;
use NetFusion\Core\Routing\Router;

/**
 * OpenAPI Controller
 *
 * Provides two endpoints:
 *   GET /api/docs     — Returns the OpenAPI 3.0 JSON specification.
 *   GET /api/docs/ui  — Renders an embedded Swagger UI HTML page (CDN-based).
 *
 * @package NetFusion\Modules\System\Controllers
 * @version 1.0.0
 */
class OpenApiController
{
    /**
     * @param Router $router Injected router (provides RouteCollection for introspection).
     */
    public function __construct(
        protected readonly Router $router,
    ) {}

    /**
     * GET /api/docs
     *
     * Returns the OpenAPI 3.0 JSON specification built from all registered routes
     * and their PHP Attribute annotations.
     *
     * @param  Request  $request
     * @return Response  JSON response with spec.
     */
    public function spec(Request $request): Response
    {
        $generator = new OpenApiGenerator(
            routes: $this->router->getRoutes(),
            info: [
                'title'         => config('app.name', 'NetFusion Framework') . ' API',
                'description'   => 'Auto-generated REST API documentation. Powered by NetFusion Framework.',
                'version'       => '1.0.0',
                'contact_name'  => 'DotNetBD',
                'contact_url'   => 'https://www.dotnetbd.com',
                'contact_email' => 'support@dotnetbd.com',
            ],
            serverUrl: $request->schemeAndHost() . '/api',
        );

        $specJson = $generator->toJson(pretty: true);

        return (new Response($specJson, 200))
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Access-Control-Allow-Origin', '*');
    }

    /**
     * GET /api/docs/ui
     *
     * Renders a fully-featured Swagger UI page powered by CDN assets.
     * Zero build step required — loads swagger-ui from unpkg.com.
     *
     * @param  Request $request
     * @return Response HTML page.
     */
    public function ui(Request $request): Response
    {
        $specUrl  = $request->schemeAndHost() . '/api/docs';
        $appName  = htmlspecialchars(config('app.name', 'NetFusion Framework'), ENT_QUOTES);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$appName} — API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f2f5; }
        #header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #fff;
            padding: 18px 32px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.4);
        }
        #header h1 { font-size: 1.4rem; font-weight: 700; letter-spacing: -.5px; }
        #header .badge {
            background: #e94560;
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: .5px;
        }
        #swagger-ui { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .swagger-ui .topbar { display: none !important; }
        .swagger-ui .info .title { color: #1a1a2e; }
    </style>
</head>
<body>
    <div id="header">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill="#e94560"/>
            <path d="M8 16 L16 8 L24 16 L16 24 Z" fill="white" opacity="0.9"/>
            <circle cx="16" cy="16" r="4" fill="white"/>
        </svg>
        <h1>{$appName} — API Documentation</h1>
        <span class="badge">OpenAPI 3.0</span>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        SwaggerUIBundle({
            url: "{$specUrl}",
            dom_id: '#swagger-ui',
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            layout: 'BaseLayout',
            deepLinking: true,
            displayRequestDuration: true,
            filter: true,
            tryItOutEnabled: true,
            persistAuthorization: true,
            requestSnippetsEnabled: true,
        });
    </script>
</body>
</html>
HTML;

        return (new Response($html, 200))
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
