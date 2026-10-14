<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\IaC\Plan;

/**
 * Execution actions for planned infrastructure mutations.
 */
enum ActionType: string
{
    case NOOP = 'noop';
    case CREATE = 'create';
    case UPDATE = 'update';
    case REPLACE = 'replace';
    case DESTROY = 'destroy';
}
