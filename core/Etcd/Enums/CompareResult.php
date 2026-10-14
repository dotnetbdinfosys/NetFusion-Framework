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

namespace NetFusion\Core\Etcd\Enums;

/**
 * Represents comparison operators for etcd transaction preconditions.
 */
enum CompareResult: string
{
    case EQUAL     = 'EQUAL';
    case GREATER   = 'GREATER';
    case LESS      = 'LESS';
    case NOT_EQUAL = 'NOT_EQUAL';
}
