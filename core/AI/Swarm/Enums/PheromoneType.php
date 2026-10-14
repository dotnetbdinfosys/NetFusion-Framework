<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Swarm/Enums/PheromoneType.php
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

namespace NetFusion\Core\AI\Swarm\Enums;

/**
 * Spatial stigmergic chemical/digital signaling marker types.
 */
enum PheromoneType: string
{
    case ATTRACTANT   = 'attractant';   // High-utility gradient attracting worker agents
    case TRAIL        = 'trail';        // Reusable route between clusters and data sources
    case ALARM        = 'alarm';        // High-failure/congestion warning repelling agents
    case EXPLORATION  = 'exploration';  // Negative bias to prevent local optima stagnation

    public function decayMultiplier(): float
    {
        return match ($this) {
            self::ALARM       => 1.5, // Alarms dissipate faster once resolved
            self::TRAIL       => 0.8, // Trails are reinforced and persistent
            self::ATTRACTANT  => 1.0,
            self::EXPLORATION => 1.2,
        };
    }
}
