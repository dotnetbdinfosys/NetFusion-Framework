<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Holographic Storage & DNA Molecular Data Encoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Storage\Molecular\Codec;

use NetFusion\Core\Storage\Molecular\Exceptions\MolecularStorageException;

class GaloisField256
{
    /** @var int[] */
    protected array $expTable = [];
    /** @var int[] */
    protected array $logTable = [];
    protected static ?self $instance = null;

    public function __construct(int $primitivePoly = 0x11D) // x^8 + x^4 + x^3 + x^2 + 1
    {
        $this->expTable = array_fill(0, 512, 0);
        $this->logTable = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $this->expTable[$i] = $x;
            $this->logTable[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= $primitivePoly;
            }
        }

        // Duplicate the exp table to avoid modulo during multiplication
        for ($i = 255; $i < 512; $i++) {
            $this->expTable[$i] = $this->expTable[$i - 255];
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add(int $a, int $b): int
    {
        return $a ^ $b;
    }

    public function sub(int $a, int $b): int
    {
        return $a ^ $b;
    }

    public function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return $this->expTable[$this->logTable[$a] + $this->logTable[$b]];
    }

    public function div(int $a, int $b): int
    {
        if ($b === 0) {
            throw new MolecularStorageException("Division by zero in Galois Field GF(2^8).");
        }
        if ($a === 0) {
            return 0;
        }
        return $this->expTable[($this->logTable[$a] - $this->logTable[$b] + 255) % 255];
    }

    public function exp(int $power): int
    {
        return $this->expTable[$power % 255];
    }

    public function log(int $elem): int
    {
        if ($elem === 0) {
            throw new MolecularStorageException("Logarithm of zero undefined in GF(2^8).");
        }
        return $this->logTable[$elem];
    }

    /**
     * Multiply two polynomials over GF(2^8)
     *
     * @param int[] $p1
     * @param int[] $p2
     * @return int[]
     */
    public function polyMul(array $p1, array $p2): array
    {
        $len1 = count($p1);
        $len2 = count($p2);
        $result = array_fill(0, $len1 + $len2 - 1, 0);

        for ($i = 0; $i < $len1; $i++) {
            for ($j = 0; $j < $len2; $j++) {
                $result[$i + $j] ^= $this->mul($p1[$i], $p2[$j]);
            }
        }

        return $result;
    }

    /**
     * Scale a polynomial by a scalar in GF(2^8)
     *
     * @param int[] $p
     * @return int[]
     */
    public function polyScale(array $p, int $scalar): array
    {
        $result = [];
        foreach ($p as $coef) {
            $result[] = $this->mul($coef, $scalar);
        }
        return $result;
    }

    /**
     * Evaluate a polynomial at point x in GF(2^8) using Horner's method
     *
     * @param int[] $p
     */
    public function polyEval(array $p, int $x): int
    {
        $y = $p[0] ?? 0;
        $count = count($p);
        for ($i = 1; $i < $count; $i++) {
            $y = $this->mul($y, $x) ^ $p[$i];
        }
        return $y;
    }
}
