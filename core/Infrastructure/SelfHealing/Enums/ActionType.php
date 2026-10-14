<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Infrastructure/SelfHealing/Enums/ActionType.php
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

namespace NetFusion\Core\Infrastructure\SelfHealing\Enums;

/**
 * Types of automated remediation actions.
 */
enum ActionType: string
{
    case RESTART_POD = 'restart_pod';
    case SCALE_OUT = 'scale_out';
    case CIRCUIT_TRIP = 'circuit_trip';
    case TRAFFIC_REROUTE = 'traffic_reroute';
    case CONFIG_ROLLBACK = 'config_rollback';
    case CACHE_FLUSH = 'cache_flush';

    public function isDisruptive(): bool
    {
        return match ($this) {
            self::RESTART_POD, self::CONFIG_ROLLBACK, self::CIRCUIT_TRIP => true,
            default => false,
        };
    }
}
