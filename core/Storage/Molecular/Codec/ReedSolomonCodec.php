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

use NetFusion\Core\Storage\Molecular\Exceptions\UncorrectableDecayException;

class ReedSolomonCodec
{
    protected GaloisField256 $gf;
    /** @var array<int, int[]> Cache of generator polynomials */
    protected array $generatorCache = [];

    public function __construct(?GaloisField256 $gf = null)
    {
        $this->gf = $gf ?? GaloisField256::getInstance();
    }

    /**
     * Build generator polynomial g(x) = (x - a^0)(x - a^1)...(x - a^(nsym-1))
     *
     * @return int[]
     */
    public function getGenerator(int $nsym): array
    {
        if (isset($this->generatorCache[$nsym])) {
            return $this->generatorCache[$nsym];
        }

        $g = [1];
        for ($i = 0; $i < $nsym; $i++) {
            $g = $this->gf->polyMul($g, [1, $this->gf->exp($i)]);
        }

        $this->generatorCache[$nsym] = $g;
        return $g;
    }

    /**
     * Systematic Reed-Solomon encode
     *
     * @param int[] $msg Array of byte values (0-255)
     * @param int $nsym Number of parity check symbols (2t)
     * @return int[] Full encoded codeword (msg + parity)
     */
    public function encode(array $msg, int $nsym = 10): array
    {
        $gen = $this->getGenerator($nsym);
        // Pad msg with nsym zeros for polynomial division
        $out = array_merge($msg, array_fill(0, $nsym, 0));
        $msgLen = count($msg);

        for ($i = 0; $i < $msgLen; $i++) {
            $coef = $out[$i];
            if ($coef !== 0) {
                for ($j = 1; $j < count($gen); $j++) {
                    $out[$i + $j] ^= $this->gf->mul($gen[$j], $coef);
                }
            }
        }

        // Systematic codeword: original message + remainder parity
        return array_merge($msg, array_slice($out, $msgLen));
    }

    /**
     * Decode and correct errors in a received codeword
     *
     * @param int[] $codeword
     * @param int $nsym Number of parity symbols
     * @return int[] Original message symbols
     * @throws UncorrectableDecayException
     */
    public function decode(array $codeword, int $nsym = 10): array
    {
        if (count($codeword) <= $nsym) {
            throw new UncorrectableDecayException("Codeword length must be strictly greater than parity symbols count.");
        }

        // 1. Calculate syndromes
        $syndromes = [];
        $hasError = false;
        for ($i = 0; $i < $nsym; $i++) {
            $s = $this->gf->polyEval($codeword, $this->gf->exp($i));
            $syndromes[] = $s;
            if ($s !== 0) {
                $hasError = true;
            }
        }

        $msgLen = count($codeword) - $nsym;

        // If all syndromes are zero, no error detected!
        if (!$hasError) {
            return array_slice($codeword, 0, $msgLen);
        }

        // 2. Berlekamp-Massey Algorithm to find Error Locator Polynomial C(x)
        $C = [1];
        $B = [1];
        $L = 0;
        $m = 1;
        $b = 1;

        for ($n = 0; $n < $nsym; $n++) {
            $d = $syndromes[$n];
            for ($i = 1; $i <= $L; $i++) {
                if (isset($C[$i])) {
                    $d ^= $this->gf->mul($C[$i], $syndromes[$n - $i]);
                }
            }

            if ($d === 0) {
                $m++;
            } else {
                $T = $C;
                $factor = $this->gf->div($d, $b);
                $shiftedB = array_fill(0, $m, 0);
                foreach ($B as $val) {
                    $shiftedB[] = $this->gf->mul($val, $factor);
                }

                $maxLen = max(count($C), count($shiftedB));
                for ($k = 0; $k < $maxLen; $k++) {
                    $cVal = $C[$k] ?? 0;
                    $bVal = $shiftedB[$k] ?? 0;
                    $C[$k] = $cVal ^ $bVal;
                }

                if (2 * $L <= $n) {
                    $L = $n + 1 - $L;
                    $B = $T;
                    $b = $d;
                    $m = 1;
                } else {
                    $m++;
                }
            }
        }

        $errCount = $L;
        if ($errCount * 2 > $nsym) {
            throw new UncorrectableDecayException("Too many errors ({$errCount}) detected; exceeds error correction capacity (" . intdiv($nsym, 2) . ").");
        }

        // 3. Chien Search to find error positions
        $cwLen = count($codeword);
        $errPos = [];
        for ($i = 0; $i < $cwLen; $i++) {
            $X = $this->gf->exp($cwLen - 1 - $i);
            $Xinv = $this->gf->div(1, $X);
            $sum = 0;
            $term = 1;
            foreach ($C as $coef) {
                $sum ^= $this->gf->mul($coef, $term);
                $term = $this->gf->mul($term, $Xinv);
            }
            if ($sum === 0) {
                $errPos[] = $i;
            }
        }

        if (count($errPos) !== $errCount) {
            throw new UncorrectableDecayException("Chien search failed: roots count mismatch (found " . count($errPos) . ", expected {$errCount}).");
        }

        // 4. Forney Algorithm to calculate error magnitudes
        // Omega(x) = (S(x) * C(x)) mod x^nsym
        $Omega = [];
        for ($i = 0; $i < $nsym; $i++) {
            $sum = 0;
            for ($j = 0; $j <= $i; $j++) {
                if (isset($C[$j], $syndromes[$i - $j])) {
                    $sum ^= $this->gf->mul($C[$j], $syndromes[$i - $j]);
                }
            }
            $Omega[] = $sum;
        }

        $corrected = $codeword;
        foreach ($errPos as $pos) {
            $X = $this->gf->exp($cwLen - 1 - $pos);
            $Xinv = $this->gf->div(1, $X);

            // Omega(X^-1)
            $omegaVal = 0;
            $term = 1;
            foreach ($Omega as $coef) {
                $omegaVal ^= $this->gf->mul($coef, $term);
                $term = $this->gf->mul($term, $Xinv);
            }

            // C'(X^-1): in GF(2), odd k: C[k] * (Xinv)^(k-1)
            $cPrimeVal = 0;
            $cLen = count($C);
            for ($k = 1; $k < $cLen; $k += 2) {
                $power = $k - 1;
                $termK = 1;
                for ($p = 0; $p < $power; $p++) {
                    $termK = $this->gf->mul($termK, $Xinv);
                }
                $cPrimeVal ^= $this->gf->mul($C[$k], $termK);
            }

            if ($cPrimeVal === 0) {
                throw new UncorrectableDecayException("Division by zero in Forney error evaluator.");
            }

            $magnitude = $this->gf->mul($X, $this->gf->div($omegaVal, $cPrimeVal));
            $corrected[$pos] ^= $magnitude;
        }

        return array_slice($corrected, 0, $msgLen);
    }
}
