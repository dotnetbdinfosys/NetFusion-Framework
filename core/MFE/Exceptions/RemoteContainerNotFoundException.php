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

namespace NetFusion\Core\MFE\Exceptions;

/**
 * Thrown when a remote container or entry point cannot be resolved or accessed.
 */
class RemoteContainerNotFoundException extends MfeException
{
    public function __construct(string $remoteName, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Remote Micro-Frontend container '{$remoteName}' not found.", $code, $previous);
    }
}
