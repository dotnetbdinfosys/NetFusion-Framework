<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/Enums/BudgetStatus.php
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

namespace NetFusion\Core\Privacy\Differential\Enums;

/**
 * Privacy Budget Status for Tenant/Dataset/User.
 */
enum BudgetStatus: string
{
    case ACTIVE    = 'active';
    case WARNING   = 'warning';
    case EXHAUSTED = 'exhausted';
}
