<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Http\Middleware;

use Closure;
use NetFusion\Core\Data\Replication\ReplicationManager;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class GeoRoutingMiddleware
{
    public function __construct(
        protected ?ReplicationManager $replication = null
    ) {
        $this->replication = $replication ?? (function_exists('app') && app()->has('replication') ? app('replication') : new ReplicationManager());
    }

    public function handle(Request $request, Closure $next): Response
    {
        $country = $this->detectCountryCode($request);
        $targetNode = $this->replication->resolveNearestRegion($country);

        $request->setAttribute('edge_region', $targetNode->id);
        $request->setAttribute('edge_node', $targetNode);

        /** @var Response $response */
        $response = $next($request);

        $response->setHeader('X-Edge-Region', $targetNode->id);
        $response->setHeader('X-Edge-Local', $targetNode->isLocal ? 'true' : 'false');

        if ($targetNode->latencyMs > 0) {
            $response->setHeader('X-Edge-Latency', sprintf('%.2fms', $targetNode->latencyMs));
        }

        return $response;
    }

    protected function detectCountryCode(Request $request): ?string
    {
        // Cloudflare, AWS CloudFront, Fastly, or standard reverse proxy headers
        $headers = [
            'HTTP_CF_IPCOUNTRY',
            'HTTP_X_GEO_COUNTRY',
            'HTTP_X_COUNTRY_CODE',
            'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        ];

        foreach ($headers as $header) {
            $val = $request->server($header);
            if (!empty($val) && strlen($val) === 2) {
                return strtoupper($val);
            }
        }

        return null;
    }
}
