<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\ZKP\Math;

/**
 * Prime finite field F_p for zk-SNARK constraint systems.
 * Default prime is BN254 scalar field order:
 * r = 21888242871839275222246405745257275088548364400416034343698204186575808495617
 */
class FiniteField
{
    public const BN254_ORDER = '21888242871839275222246405745257275088548364400416034343698204186575808495617';

    private BigInt $modulus;

    public function __construct(string|int|BigInt $modulus = self::BN254_ORDER)
    {
        $this->modulus = $modulus instanceof BigInt ? $modulus : new BigInt($modulus);
    }

    public static function bn254(): self
    {
        return new self(self::BN254_ORDER);
    }

    public static function bn254Scalar(): self
    {
        return new self(self::BN254_ORDER);
    }

    public function getModulus(): BigInt
    {
        return $this->modulus;
    }

    public function element(int|string|BigInt $val): FieldElement
    {
        return new FieldElement($val, $this);
    }

    public function zero(): FieldElement
    {
        return new FieldElement(0, $this);
    }

    public function one(): FieldElement
    {
        return new FieldElement(1, $this);
    }

    public function random(): FieldElement
    {
        // Random field element in [0, modulus - 1]
        $bytes = random_bytes(32);
        $val = gmp_import($bytes);
        return new FieldElement($val, $this);
    }
}
