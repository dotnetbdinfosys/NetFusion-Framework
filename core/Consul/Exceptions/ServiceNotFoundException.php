<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Consul\Exceptions;

/**
 * Thrown when a queried service or instance is not registered in the catalog.
 */
class ServiceNotFoundException extends ConsulException
{
    public function __construct(string $serviceName, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Service '{$serviceName}' not found in Consul service catalog.", $code, $previous);
    }
}
