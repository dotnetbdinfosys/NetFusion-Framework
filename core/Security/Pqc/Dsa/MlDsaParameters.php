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

namespace NetFusion\Core\Security\Pqc\Dsa;

/**
 * Class MlDsaParameters
 *
 * Defines standard NIST FIPS 204 (ML-DSA / Crystals-Dilithium) parameter sets.
 */
class MlDsaParameters
{
    public const ML_DSA_44 = 'ml-dsa-44';
    public const ML_DSA_65 = 'ml-dsa-65';
    public const ML_DSA_87 = 'ml-dsa-87';

    public function __construct(
        public readonly string $name,
        public readonly int $k,
        public readonly int $l,
        public readonly int $eta,
        public readonly int $gamma1,
        public readonly int $gamma2,
        public readonly int $beta,
        public readonly int $omega
    ) {
    }

    public static function mlDsa44(): self
    {
        return new self(self::ML_DSA_44, 4, 4, 2, 1 << 17, (8380417 - 1) / 88, 78, 80);
    }

    public static function mlDsa65(): self
    {
        return new self(self::ML_DSA_65, 6, 5, 4, 1 << 19, (8380417 - 1) / 32, 196, 55);
    }

    public static function mlDsa87(): self
    {
        return new self(self::ML_DSA_87, 8, 7, 2, 1 << 19, (8380417 - 1) / 32, 120, 75);
    }

    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            self::ML_DSA_44, 'dilithium2' => self::mlDsa44(),
            self::ML_DSA_87, 'dilithium5' => self::mlDsa87(),
            default                       => self::mlDsa65(),
        };
    }
}
