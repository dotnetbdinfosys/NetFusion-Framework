<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Enums/SchemeType.php
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

namespace NetFusion\Core\Security\Homomorphic\Enums;

/**
 * Homomorphic Encryption Scheme Types.
 */
enum SchemeType: string
{
    case BFV  = 'bfv';  // Brakerski-Fan-Vercauteren (Exact modular integer arithmetic)
    case CKKS = 'ckks'; // Cheon-Kim-Kim-Song (Approximate floating-point vector arithmetic)
    case LWE  = 'lwe';  // Learning With Errors (Basic scalar addition and scalar multiplication)

    public function description(): string
    {
        return match ($this) {
            self::BFV  => 'Brakerski-Fan-Vercauteren: Exact modular integer Ring-LWE scheme',
            self::CKKS => 'Cheon-Kim-Kim-Song: Fixed-point approximate floating vector scheme',
            self::LWE  => 'Learning With Errors: Scalar lattice-based additive scheme',
        };
    }
}
