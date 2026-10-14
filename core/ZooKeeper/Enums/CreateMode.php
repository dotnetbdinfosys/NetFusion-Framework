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

namespace NetFusion\Core\ZooKeeper\Enums;

/**
 * ZooKeeper Node Mode (Persistent, Ephemeral, Sequential).
 */
enum CreateMode: int
{
    case PERSISTENT           = 0;
    case PERSISTENT_SEQUENTIAL = 1;
    case EPHEMERAL            = 2;
    case EPHEMERAL_SEQUENTIAL  = 3;

    public function isEphemeral(): bool
    {
        return $this === self::EPHEMERAL || $this === self::EPHEMERAL_SEQUENTIAL;
    }

    public function isSequential(): bool
    {
        return $this === self::PERSISTENT_SEQUENTIAL || $this === self::EPHEMERAL_SEQUENTIAL;
    }
}
