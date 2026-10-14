<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Enums/QubitBasis.php
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
 * Measurement basis for Qubit / Photon polarization.
 */
enum QubitBasis: string
{
    case RECTILINEAR = '+'; // 0° / 90°
    case DIAGONAL    = 'x'; // 45° / 135°

    public function isCompatible(self $other): bool
    {
        return $this === $other;
    }
}
