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

namespace NetFusion\Core\Edge\Exceptions;

class IsolateResourceExceededException extends EdgeException
{
    public function __construct(string $message = 'Isolate resource bounds exceeded (CPU or Memory limit violated)')
    {
        parent::__construct($message);
    }
}
