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

namespace NetFusion\Core\Gateway\Exceptions;

class UpstreamUnavailableException extends GatewayException
{
    public function __construct(
        string $message = 'No healthy upstream available',
        private readonly string $routeId = '',
        int $code = 503,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRouteId(): string
    {
        return $this->routeId;
    }
}
