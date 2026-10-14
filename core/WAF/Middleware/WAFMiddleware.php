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

namespace NetFusion\Core\WAF\Middleware;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\WAF\WAFManager;
use Closure;

class WAFMiddleware
{
    public function __construct(
        protected ?WAFManager $waf = null
    ) {
    }

    /**
     * Handle incoming request through WAF inspection pipeline.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var WAFManager $waf */
        $waf = $this->waf ?? (function_exists('app') && app()->has('waf') ? app('waf') : new WAFManager());

        $ip = $request->ip() ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $method = $request->method();
        $uri = $request->uri();
        $headers = $this->extractHeaders($request);
        $query = $request->query() ?? [];
        $body = $request->post() ?: ($request->json() ?: $request->all());
        $cookies = $request->cookies();

        $context = $waf->inspect(
            ip: $ip,
            method: $method,
            uri: $uri,
            headers: $headers,
            query: $query,
            body: $body,
            cookies: $cookies
        );

        if ($context->isBlocked()) {
            return Response::json([
                'status' => 403,
                'error' => 'Forbidden: Request blocked by Web Application Firewall (WAF)',
                'reason' => $context->getBlockReason(),
                'rule_id' => $context->getTriggeredRuleId(),
                'ip' => $ip,
                'timestamp' => $context->getTimestamp(),
            ], 403);
        }

        if ($context->isChallenged()) {
            $pow = $waf->getChallengeEngine()->createPoWChallenge(4);
            return Response::json([
                'status' => 429,
                'error' => 'Security Challenge Required',
                'challenge_type' => 'pow',
                'challenge_data' => $pow,
                'ip' => $ip,
            ], 429);
        }

        return $next($request);
    }

    /**
     * Extract normalized headers array from request.
     *
     * @return array<string, string>
     */
    protected function extractHeaders(Request $request): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $val) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = (string)$val;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string)$_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string)$_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
