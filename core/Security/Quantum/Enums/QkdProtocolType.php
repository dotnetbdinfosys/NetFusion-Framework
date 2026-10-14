<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Enums/QkdProtocolType.php
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

namespace NetFusion\Core\Security\Quantum\Enums;

/**
 * Quantum Key Distribution Protocol Types.
 */
enum QkdProtocolType: string
{
    case BB84 = 'bb84'; // Bennett & Brassard 1984 (Prepare-and-Measure)
    case E91  = 'e91';  // Ekert 1991 (Entanglement-based Bell Pairs)

    public function maxTheoreticalQber(): float
    {
        // Abort if QBER > 11.0% (Shor-Preskill security bound)
        return match ($this) {
            self::BB84 => 0.11,
            self::E91  => 0.146,
        };
    }
}
