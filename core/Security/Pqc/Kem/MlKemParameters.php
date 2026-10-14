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

namespace NetFusion\Core\Security\Pqc\Kem;

/**
 * Class MlKemParameters
 *
 * Defines standard NIST FIPS 203 (ML-KEM / Crystals-Kyber) parameter sets.
 */
class MlKemParameters
{
    public const ML_KEM_512  = 'ml-kem-512';
    public const ML_KEM_768  = 'ml-kem-768';
    public const ML_KEM_1024 = 'ml-kem-1024';

    public function __construct(
        public readonly string $name,
        public readonly int $k,
        public readonly int $eta1,
        public readonly int $eta2,
        public readonly int $du,
        public readonly int $dv
    ) {
    }

    public static function mlKem512(): self
    {
        return new self(self::ML_KEM_512, 2, 3, 2, 10, 4);
    }

    public static function mlKem768(): self
    {
        return new self(self::ML_KEM_768, 3, 2, 2, 10, 4);
    }

    public static function mlKem1024(): self
    {
        return new self(self::ML_KEM_1024, 4, 2, 2, 11, 5);
    }

    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            self::ML_KEM_512, 'kyber512'   => self::mlKem512(),
            self::ML_KEM_1024, 'kyber1024' => self::mlKem1024(),
            default                        => self::mlKem768(),
        };
    }
}
