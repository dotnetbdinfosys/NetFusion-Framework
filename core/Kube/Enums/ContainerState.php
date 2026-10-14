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
 * Represents the execution state of an individual container within a Pod.
 */
enum ContainerState: string
{
    case WAITING    = 'Waiting';
    case RUNNING    = 'Running';
    case TERMINATED = 'Terminated';
}
