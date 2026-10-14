<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Enums/PhotonState.php
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
 * Photon polarization state.
 */
enum PhotonState: string
{
    case HORIZONTAL   = '|0>';  // Bit 0, Rectilinear
    case VERTICAL     = '|1>';  // Bit 1, Rectilinear
    case DIAGONAL_45  = '|+>';  // Bit 0, Diagonal
    case DIAGONAL_135 = '|->';  // Bit 1, Diagonal

    public function getBasis(): QubitBasis
    {
        return match ($this) {
            self::HORIZONTAL, self::VERTICAL => QubitBasis::RECTILINEAR,
            self::DIAGONAL_45, self::DIAGONAL_135 => QubitBasis::DIAGONAL,
        };
    }

    public function getBit(): int
    {
        return match ($this) {
            self::HORIZONTAL, self::DIAGONAL_45 => 0,
            self::VERTICAL, self::DIAGONAL_135 => 1,
        };
    }

    public static function fromBitAndBasis(int $bit, QubitBasis $basis): self
    {
        if ($basis === QubitBasis::RECTILINEAR) {
            return $bit === 0 ? self::HORIZONTAL : self::VERTICAL;
        }
        return $bit === 0 ? self::DIAGONAL_45 : self::DIAGONAL_135;
    }
}
