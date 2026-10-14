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

namespace NetFusion\Core\SDUI\Enums;

enum PlatformType: string
{
    case ALL          = 'all';
    case WEB          = 'web';
    case IOS          = 'ios';
    case ANDROID      = 'android';
    case FLUTTER      = 'flutter';
    case REACT_NATIVE = 'react_native';
    case DESKTOP      = 'desktop';
}
