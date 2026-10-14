<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Routing/RouteMatch.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Immutable value object encapsulating route matching results (found, 404, or 405).
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
| File      : core/Routing/RouteMatch.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Immutable value object encapsulating route matching results (found, 404, or 405).
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
| File      : core/Routing/RouteMatch.php
| Module    : Core\Routing
| Namespace : NetFusion\Core\Routing
| Purpose   : Immutable value object encapsulating route matching results (found, 404, or 405).
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

namespace NetFusion\Core\Routing;

/**
 * NetFusion Route Match Result
 *
 * Encapsulates the outcome of a route matching attempt.
 * Uses a typed enum-style status to communicate results clearly
 * without throwing exceptions in the hot path.
 *
 * @package NetFusion\Core\Routing
 * @version 1.0.0
 */
final class RouteMatch
{
    /**
     * Match status constants.
     */
    public const STATUS_FOUND               = 'found';
    public const STATUS_NOT_FOUND           = 'not_found';
    public const STATUS_METHOD_NOT_ALLOWED  = 'method_not_allowed';

    /**
     * @param string                $status          One of the STATUS_* constants.
     * @param Route|null            $route           The matched route (if found).
     * @param array<string, string> $params          Extracted route parameters (if found).
     * @param string[]              $allowedMethods  Allowed methods (if 405).
     */
    private function __construct(
        public readonly string  $status,
        public readonly ?Route  $route,
        public readonly array   $params,
        public readonly array   $allowedMethods,
    ) {}

    /**
     * Create a successful match result.
     *
     * @param  Route                $route  The matched route.
     * @param  array<string,string> $params The extracted URL parameters.
     * @return self
     */
    public static function found(Route $route, array $params = []): self
    {
        return new self(
            status         : self::STATUS_FOUND,
            route          : $route,
            params         : $params,
            allowedMethods : [],
        );
    }

    /**
     * Create a 405 Method Not Allowed result.
     *
     * @param  string[] $allowedMethods HTTP methods that ARE allowed.
     * @return self
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(
            status         : self::STATUS_METHOD_NOT_ALLOWED,
            route          : null,
            params         : [],
            allowedMethods : $allowedMethods,
        );
    }

    /**
     * Create a 404 Not Found result.
     *
     * @return self
     */
    public static function notFound(): self
    {
        return new self(
            status         : self::STATUS_NOT_FOUND,
            route          : null,
            params         : [],
            allowedMethods : [],
        );
    }

    /**
     * Whether the route was successfully matched.
     */
    public function isFound(): bool
    {
        return $this->status === self::STATUS_FOUND;
    }

    /**
     * Whether the route exists but the method is not allowed (405).
     */
    public function isMethodNotAllowed(): bool
    {
        return $this->status === self::STATUS_METHOD_NOT_ALLOWED;
    }

    /**
     * Whether no route was found (404).
     */
    public function isNotFound(): bool
    {
        return $this->status === self::STATUS_NOT_FOUND;
    }
}
