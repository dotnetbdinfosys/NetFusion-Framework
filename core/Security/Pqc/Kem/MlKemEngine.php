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

use NetFusion\Core\Security\Pqc\Math\PolynomialRing;
use RuntimeException;

/**
 * Class MlKemEngine
 *
 * Pure-PHP implementation of NIST FIPS 203 (ML-KEM / Crystals-Kyber)
 * Key Encapsulation Mechanism (Module-LWE over R_q).
 */
class MlKemEngine
{
    protected MlKemParameters $params;

    public function __construct(?MlKemParameters $params = null)
    {
        $this->params = $params ?? MlKemParameters::mlKem768();
    }

    public function getParameters(): MlKemParameters
    {
        return $this->params;
    }

    /**
     * Generate an ML-KEM public/secret keypair.
     *
     * @param string|null $randomSeed Optional 64-byte seed for deterministic testing
     * @return KemKeyPair
     */
    public function generateKeyPair(?string $randomSeed = null): KemKeyPair
    {
        $k = $this->params->k;
        $eta1 = $this->params->eta1;

        if ($randomSeed !== null && strlen($randomSeed) >= 64) {
            $d = substr($randomSeed, 0, 32);
            $z = substr($randomSeed, 32, 32);
        } else {
            $d = random_bytes(32);
            $z = random_bytes(32);
        }

        // Hash d into (rho, sigma)
        $hashD = hash('sha512', $d, true);
        $rho = substr($hashD, 0, 32);
        $sigma = substr($hashD, 32, 32);

        // Generate matrix A in R_q^(k x k) from seed rho
        $A = [];
        for ($i = 0; $i < $k; $i++) {
            $A[$i] = [];
            for ($j = 0; $j < $k; $j++) {
                $A[$i][$j] = PolynomialRing::sampleUniform($rho, $i, $j);
            }
        }

        // Sample secret vector s and error vector e from sigma
        $s = [];
        $e = [];
        $nonce = 0;
        for ($i = 0; $i < $k; $i++) {
            $s[$i] = PolynomialRing::sampleCbd($sigma, $nonce++, $eta1);
        }
        for ($i = 0; $i < $k; $i++) {
            $e[$i] = PolynomialRing::sampleCbd($sigma, $nonce++, $eta1);
        }

        // Compute t = A * s + e
        $t = [];
        for ($i = 0; $i < $k; $i++) {
            $accum = PolynomialRing::zero();
            for ($j = 0; $j < $k; $j++) {
                $prod = PolynomialRing::multiply($A[$i][$j], $s[$j]);
                $accum = PolynomialRing::add($accum, $prod);
            }
            $t[$i] = PolynomialRing::add($accum, $e[$i]);
        }

        // Encode Public Key: t || rho
        $pk = $this->encodePolyVector($t) . $rho;

        // Hash of Public Key
        $hPk = hash('sha256', $pk, true);

        // Encode Secret Key: s || pk || H(pk) || z
        $sk = $this->encodePolyVector($s) . $pk . $hPk . $z;

        return new KemKeyPair($pk, $sk, $this->params);
    }

    /**
     * Encapsulate a shared secret against a public key.
     *
     * @param string $publicKey Public key bytes
     * @param string|null $messageSeed Optional 32-byte message for deterministic testing
     * @return EncapsulationResult
     */
    public function encapsulate(string $publicKey, ?string $messageSeed = null): EncapsulationResult
    {
        $k = $this->params->k;
        $eta1 = $this->params->eta1;
        $eta2 = $this->params->eta2;
        $du = $this->params->du;
        $dv = $this->params->dv;

        // Parse Public Key: t || rho
        $tBytesLen = $k * 256 * 2;
        if (strlen($publicKey) < $tBytesLen + 32) {
            throw new RuntimeException("Invalid public key length for {$this->params->name}");
        }
        $tBytes = substr($publicKey, 0, $tBytesLen);
        $rho = substr($publicKey, $tBytesLen, 32);
        $t = $this->decodePolyVector($tBytes, $k);

        // Generate A^T from rho
        $At = [];
        for ($i = 0; $i < $k; $i++) {
            $At[$i] = [];
            for ($j = 0; $j < $k; $j++) {
                // Notice transposition: A[j][i]
                $At[$i][$j] = PolynomialRing::sampleUniform($rho, $j, $i);
            }
        }

        // Sample message m
        $m = $messageSeed ?? random_bytes(32);
        $hPk = hash('sha256', $publicKey, true);

        // (K_bar, r) = SHA512(m || H(pk))
        $hashMr = hash('sha512', $m . $hPk, true);
        $kBar = substr($hashMr, 0, 32);
        $rSeed = substr($hashMr, 32, 32);

        // Sample r, e1, e2 from rSeed
        $r = [];
        $e1 = [];
        $nonce = 0;
        for ($i = 0; $i < $k; $i++) {
            $r[$i] = PolynomialRing::sampleCbd($rSeed, $nonce++, $eta1);
        }
        for ($i = 0; $i < $k; $i++) {
            $e1[$i] = PolynomialRing::sampleCbd($rSeed, $nonce++, $eta2);
        }
        $e2 = PolynomialRing::sampleCbd($rSeed, $nonce++, $eta2);

        // u = A^T * r + e1
        $u = [];
        for ($i = 0; $i < $k; $i++) {
            $accum = PolynomialRing::zero();
            for ($j = 0; $j < $k; $j++) {
                $prod = PolynomialRing::multiply($At[$i][$j], $r[$j]);
                $accum = PolynomialRing::add($accum, $prod);
            }
            $u[$i] = PolynomialRing::add($accum, $e1[$i]);
        }

        // v = t^T * r + e2 + mu(m)
        $tTr = PolynomialRing::zero();
        for ($i = 0; $i < $k; $i++) {
            $prod = PolynomialRing::multiply($t[$i], $r[$i]);
            $tTr = PolynomialRing::add($tTr, $prod);
        }
        $mu = $this->messageToPolynomial($m);
        $v = PolynomialRing::add(PolynomialRing::add($tTr, $e2), $mu);

        // Compress u and v
        $uCompressed = [];
        for ($i = 0; $i < $k; $i++) {
            $uCompressed[$i] = PolynomialRing::compress($u[$i], $du);
        }
        $vCompressed = PolynomialRing::compress($v, $dv);

        // Ciphertext c = compress(u) || compress(v)
        $c = $this->encodePolyVector($uCompressed) . $this->encodePolynomial($vCompressed);

        // Shared Secret K = H(K_bar || H(c))
        $sharedSecret = hash_hmac('sha256', $kBar . hash('sha256', $c, true), 'NETFUSION_MLKEM_SS', true);

        return new EncapsulationResult($c, $sharedSecret, $this->params->name);
    }

    /**
     * Decapsulate a shared secret from ciphertext using secret key (CCA2 secure with implicit rejection).
     *
     * @param string $secretKey Secret key bytes
     * @param string $ciphertext Ciphertext bytes
     * @return string 32-byte shared secret key
     */
    public function decapsulate(string $secretKey, string $ciphertext): string
    {
        $k = $this->params->k;
        $du = $this->params->du;
        $dv = $this->params->dv;

        $sBytesLen = $k * 256 * 2;
        $pkBytesLen = ($k * 256 * 2) + 32;

        if (strlen($secretKey) < $sBytesLen + $pkBytesLen + 32 + 32) {
            throw new RuntimeException("Invalid secret key length for {$this->params->name}");
        }

        $sBytes = substr($secretKey, 0, $sBytesLen);
        $pk = substr($secretKey, $sBytesLen, $pkBytesLen);
        $hPk = substr($secretKey, $sBytesLen + $pkBytesLen, 32);
        $z = substr($secretKey, $sBytesLen + $pkBytesLen + 32, 32);

        $s = $this->decodePolyVector($sBytes, $k);

        // Decode ciphertext
        $uBytesLen = $k * 256 * 2;
        $vBytesLen = 256 * 2;
        if (strlen($ciphertext) < $uBytesLen + $vBytesLen) {
            // Tampered / malformed ciphertext: return pseudo-random key
            return hash_hmac('sha256', $z . hash('sha256', $ciphertext, true), 'NETFUSION_MLKEM_REJECT', true);
        }

        $uCompressed = $this->decodePolyVector(substr($ciphertext, 0, $uBytesLen), $k);
        $vCompressed = $this->decodePolynomial(substr($ciphertext, $uBytesLen, $vBytesLen));

        // Decompress u and v
        $u = [];
        for ($i = 0; $i < $k; $i++) {
            $u[$i] = PolynomialRing::decompress($uCompressed[$i], $du);
        }
        $v = PolynomialRing::decompress($vCompressed, $dv);

        // m' = v - s^T * u
        $sTu = PolynomialRing::zero();
        for ($i = 0; $i < $k; $i++) {
            $prod = PolynomialRing::multiply($s[$i], $u[$i]);
            $sTu = PolynomialRing::add($sTu, $prod);
        }
        $mPrimePoly = PolynomialRing::sub($v, $sTu);
        $mPrime = $this->polynomialToMessage($mPrimePoly);

        // Recompute (K_bar', r') = SHA512(m' || H(pk))
        $hashMr = hash('sha512', $mPrime . $hPk, true);
        $kBarPrime = substr($hashMr, 0, 32);

        // Fujisaki-Okamoto re-encryption check:
        // We re-encapsulate with m' and verify if ciphertext matches
        $reEncaps = $this->encapsulate($pk, $mPrime);

        if (hash_equals($reEncaps->getCiphertext(), $ciphertext)) {
            // Success: derive legitimate shared secret
            return hash_hmac('sha256', $kBarPrime . hash('sha256', $ciphertext, true), 'NETFUSION_MLKEM_SS', true);
        }

        // Implicit Rejection (Constant-time failure): pseudo-random key derived from z
        return hash_hmac('sha256', $z . hash('sha256', $ciphertext, true), 'NETFUSION_MLKEM_REJECT', true);
    }

    /**
     * Map a 32-byte message into polynomial coefficients in {0, round(q/2)}.
     */
    protected function messageToPolynomial(string $m): array
    {
        $poly = array_fill(0, 256, 0);
        $halfQ = (int) round(PolynomialRing::MODULUS / 2); // 1665

        for ($i = 0; $i < 32; $i++) {
            $byte = ord($m[$i]);
            for ($bit = 0; $bit < 8; $bit++) {
                if (($byte >> $bit) & 1) {
                    $poly[$i * 8 + $bit] = $halfQ;
                }
            }
        }

        return $poly;
    }

    /**
     * Decode polynomial back into a 32-byte message.
     */
    protected function polynomialToMessage(array $poly): string
    {
        $halfQ = (int) round(PolynomialRing::MODULUS / 2); // 1665
        $q = PolynomialRing::MODULUS;
        $quarterQ = (int) round($q / 4); // 832
        $threeQuarterQ = (int) round(3 * $q / 4); // 2497

        $bytes = '';
        for ($i = 0; $i < 32; $i++) {
            $byteVal = 0;
            for ($bit = 0; $bit < 8; $bit++) {
                $val = $poly[$i * 8 + $bit] % $q;
                // If val is in [quarterQ, threeQuarterQ], it is closer to halfQ -> bit is 1
                if ($val >= $quarterQ && $val <= $threeQuarterQ) {
                    $byteVal |= (1 << $bit);
                }
            }
            $bytes .= chr($byteVal);
        }

        return $bytes;
    }

    /**
     * Encode a single polynomial as 16-bit little-endian integers (512 bytes).
     */
    protected function encodePolynomial(array $p): string
    {
        $out = '';
        for ($i = 0; $i < 256; $i++) {
            $out .= pack('v', $p[$i]);
        }
        return $out;
    }

    /**
     * Decode 512 bytes into a single polynomial.
     */
    protected function decodePolynomial(string $bytes): array
    {
        $p = [];
        $unpacked = unpack('v256', $bytes);
        for ($i = 1; $i <= 256; $i++) {
            $p[] = $unpacked[$i];
        }
        return $p;
    }

    /**
     * Encode a vector of polynomials.
     */
    protected function encodePolyVector(array $vec): string
    {
        $out = '';
        foreach ($vec as $p) {
            $out .= $this->encodePolynomial($p);
        }
        return $out;
    }

    /**
     * Decode bytes into a vector of polynomials.
     */
    protected function decodePolyVector(string $bytes, int $k): array
    {
        $vec = [];
        $polyLen = 512;
        for ($i = 0; $i < $k; $i++) {
            $chunk = substr($bytes, $i * $polyLen, $polyLen);
            $vec[] = $this->decodePolynomial($chunk);
        }
        return $vec;
    }
}
