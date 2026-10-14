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

namespace NetFusion\Core\Media\Enums;

enum SimulcastLayer: string
{
    case HIGH = 'high';      // 1080p / 720p ~ 2500 kbps
    case MEDIUM = 'medium';  // 480p / 360p ~ 700 kbps
    case LOW = 'low';        // 240p / 180p ~ 150 kbps
}
