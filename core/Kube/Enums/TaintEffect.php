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

namespace NetFusion\Core\Kube\Enums;

/**
 * Node Taint and Pod Toleration effect.
 */
enum TaintEffect: string
{
    case NO_SCHEDULE        = 'NoSchedule';
    case PREFER_NO_SCHEDULE = 'PreferNoSchedule';
    case NO_EXECUTE         = 'NoExecute';
}
