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

namespace NetFusion\Core\Auth\Policy\Middleware;

use NetFusion\Core\Auth\Policy\AccessManager;
use NetFusion\Core\Http\Middleware\MiddlewareInterface;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class AuthorizePolicyMiddleware implements MiddlewareInterface
{
    protected AccessManager $access;
    protected ?string $action = null;
    protected ?string $resourceType = null;

    public function __construct(
        ?AccessManager $access = null,
        ?string $parameters = null,
        ?string $action = null,
        ?string $resourceType = null
    ) {
        $this->access = $access ?? (function_exists('app') && app()->has('access.manager') ? app('access.manager') : new AccessManager(function_exists('app') ? app() : null));

        if ($action !== null) {
            $this->action = $action;
            $this->resourceType = $resourceType ?? '*';
        } elseif ($parameters !== null && $parameters !== '') {
            $parts = explode(',', $parameters);
            $this->action = trim($parts[0] ?? '');
            $this->resourceType = trim($parts[1] ?? '*');
        }
    }

    public function handle(Request $request, callable $next, ?string $action = null, ?string $resourceParam = null): Response
    {
        $act = $action ?? $this->action;
        $resType = $resourceParam ?? $this->resourceType ?? '*';

        if (empty($act)) {
            return $next($request);
        }

        $subject = $request->getAttribute('user')
            ?? $request->getAttribute('auth_user')
            ?? ['id' => $request->getAttribute('user_id', 'anonymous'), 'ip' => $request->ip()];

        $environment = [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'timestamp' => time(),
            'hour' => (int)date('H'),
        ];

        // Check if resourceParam refers to a request attribute
        $resource = is_string($resType) && $request->getAttribute($resType) !== null
            ? $request->getAttribute($resType)
            : $resType;

        if (!$this->access->can($subject, $act, $resource, $environment)) {
            return new Response(json_encode([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to perform this action.',
            ]), 403, ['Content-Type' => 'application/json']);
        }

        return $next($request);
    }
}
