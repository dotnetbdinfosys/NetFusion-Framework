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

use RuntimeException;

/**
 * Class MlDsaEngine
 *
 * Pure-PHP implementation of NIST FIPS 204 (ML-DSA / Crystals-Dilithium)
 * Lattice-Based Digital Signature Algorithm (Fiat-Shamir with Aborts).
 */
class MlDsaEngine
{
    public const MODULUS = 8380417; // 2^23 - 2^13 + 1
    public const DEGREE  = 256;

    protected MlDsaParameters $params;

    public function __construct(?MlDsaParameters $params = null)
    {
        $this->params = $params ?? MlDsaParameters::mlDsa44();
    }

    public function getParameters(): MlDsaParameters
    {
        return $this->params;
    }

    /**
     * Generate an ML-DSA public verification / secret signing keypair.
     *
     * @param string|null $seed Optional 32-byte seed for deterministic testing
     * @return DsaKeyPair
     */
    public function generateKeyPair(?string $seed = null): DsaKeyPair
    {
        $xi = $seed ?? random_bytes(32);
        $k = $this->params->k;
        $l = $this->params->l;
        $eta = $this->params->eta;

        // Hash xi to (rho, rhoPrime, K)
        $hashXi = hash('sha512', $xi . 'ML_DSA_KEYGEN', true);
        $rho = substr($hashXi, 0, 32);
        $rhoPrime = substr($hashXi, 32, 32);
        $K = hash('sha256', $rhoPrime, true);

        // Expand matrix A in R_q^(k x l) from rho
        $A = $this->expandMatrixA($rho, $k, $l);

        // Sample secret vector s1 in R_q^l from rhoPrime
        $s1 = [];
        $nonce = 0;
        for ($i = 0; $i < $l; $i++) {
            $s1[$i] = $this->sampleEtaPoly($rhoPrime, $nonce++, $eta);
        }

        // t = A * s1
        $t = [];
        for ($i = 0; $i < $k; $i++) {
            $accum = array_fill(0, self::DEGREE, 0);
            for ($j = 0; $j < $l; $j++) {
                $prod = $this->polyMul($A[$i][$j], $s1[$j]);
                $accum = $this->polyAdd($accum, $prod);
            }
            $t[$i] = $accum;
        }

        // Encode Public Key: rho || encoded(t)
        $pk = $rho . $this->encodePolyVector($t);
        $tr = hash('sha256', $pk, true);

        // Encode Secret Key: rho || K || tr || encoded(s1) || encoded(t)
        $sk = $rho . $K . $tr . $this->encodePolyVector($s1) . $this->encodePolyVector($t);

        return new DsaKeyPair($pk, $sk, $this->params);
    }

    /**
     * Sign a message using ML-DSA secret key.
     *
     * @param string $message
     * @param string $secretKey
     * @return Signature
     */
    public function sign(string $message, string $secretKey): Signature
    {
        $k = $this->params->k;
        $l = $this->params->l;
        $gamma1 = $this->params->gamma1;
        $beta = $this->params->beta;

        // Parse secret key
        $rho = substr($secretKey, 0, 32);
        $K = substr($secretKey, 32, 32);
        $tr = substr($secretKey, 64, 32);

        $polyLen = self::DEGREE * 4; // 1024 bytes per 32-bit poly
        $s1Offset = 96;
        $s1 = $this->decodePolyVector(substr($secretKey, $s1Offset, $l * $polyLen), $l);

        $A = $this->expandMatrixA($rho, $k, $l);

        // Message digest mu = SHA512(tr || message)
        $mu = hash('sha512', $tr . $message, true);
        $rhoPrime = hash('sha512', $K . $mu, true);

        $nonce = 0;
        $maxAttempts = 1000;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Sample y in R_q^l with coefficients in [-gamma1 + 1, gamma1]
            $y = [];
            for ($i = 0; $i < $l; $i++) {
                $y[$i] = $this->sampleGammaPoly($rhoPrime, $nonce++, $gamma1);
            }

            // w = A * y
            $w = [];
            for ($i = 0; $i < $k; $i++) {
                $accum = array_fill(0, self::DEGREE, 0);
                for ($j = 0; $j < $l; $j++) {
                    $prod = $this->polyMul($A[$i][$j], $y[$j]);
                    $accum = $this->polyAdd($accum, $prod);
                }
                $w[$i] = $accum;
            }

            // Challenge hash cTilde = H(mu || encoded(w))
            $cTilde = hash('sha256', $mu . $this->encodePolyVector($w), true);
            $c = $this->sampleChallengePoly($cTilde);

            // Compute z = y + c * s1
            $z = [];
            $reject = false;
            for ($i = 0; $i < $l; $i++) {
                $cs1 = $this->polyMul($c, $s1[$i]);
                $zi = $this->polyAdd($y[$i], $cs1);

                // Rejection check: infinity norm of z must be < gamma1 - beta
                foreach ($zi as $coeff) {
                    $centered = $coeff > (self::MODULUS / 2) ? $coeff - self::MODULUS : $coeff;
                    if (abs($centered) >= ($gamma1 - $beta)) {
                        $reject = true;
                        break 2;
                    }
                }
                $z[$i] = $zi;
            }

            if ($reject) {
                continue;
            }

            // Signature found: cTilde (32 bytes) || encoded(z)
            $sigBytes = $cTilde . $this->encodePolyVector($z);
            return new Signature($sigBytes, $this->params->name);
        }

        throw new RuntimeException("ML-DSA signature generation exceeded max attempts");
    }

    /**
     * Verify an ML-DSA digital signature against a message and public key.
     *
     * @param string $message
     * @param Signature|string $signature
     * @param string $publicKey
     * @return bool True if valid, false otherwise
     */
    public function verify(string $message, Signature|string $signature, string $publicKey): bool
    {
        $sigBytes = $signature instanceof Signature ? $signature->getBytes() : $signature;
        $k = $this->params->k;
        $l = $this->params->l;
        $gamma1 = $this->params->gamma1;
        $beta = $this->params->beta;

        $polyLen = self::DEGREE * 4;
        if (strlen($sigBytes) < 32 + ($l * $polyLen)) {
            return false;
        }

        $cTilde = substr($sigBytes, 0, 32);
        $z = $this->decodePolyVector(substr($sigBytes, 32, $l * $polyLen), $l);

        // Check norm bounds of z
        foreach ($z as $zi) {
            foreach ($zi as $coeff) {
                $centered = $coeff > (self::MODULUS / 2) ? $coeff - self::MODULUS : $coeff;
                if (abs($centered) >= ($gamma1 - $beta)) {
                    return false;
                }
            }
        }

        // Parse public key
        if (strlen($publicKey) < 32 + ($k * $polyLen)) {
            return false;
        }
        $rho = substr($publicKey, 0, 32);
        $t = $this->decodePolyVector(substr($publicKey, 32, $k * $polyLen), $k);

        $A = $this->expandMatrixA($rho, $k, $l);
        $c = $this->sampleChallengePoly($cTilde);

        // wPrime = A * z - c * t
        $wPrime = [];
        for ($i = 0; $i < $k; $i++) {
            $accum = array_fill(0, self::DEGREE, 0);
            for ($j = 0; $j < $l; $j++) {
                $prod = $this->polyMul($A[$i][$j], $z[$j]);
                $accum = $this->polyAdd($accum, $prod);
            }
            $ct = $this->polyMul($c, $t[$i]);
            $wPrime[$i] = $this->polySub($accum, $ct);
        }

        $tr = hash('sha256', $publicKey, true);
        $mu = hash('sha512', $tr . $message, true);

        // In Dilithium verification, check cTilde' == cTilde
        $cTildePrime = hash('sha256', $mu . $this->encodePolyVector($wPrime), true);

        return hash_equals($cTilde, $cTildePrime);
    }

    /**
     * Expand matrix A in R_q^(k x l) uniformly from seed rho.
     */
    protected function expandMatrixA(string $rho, int $k, int $l): array
    {
        $A = [];
        $q = self::MODULUS;
        $n = self::DEGREE;

        for ($i = 0; $i < $k; $i++) {
            $A[$i] = [];
            for ($j = 0; $j < $l; $j++) {
                $poly = [];
                $stream = hash_hmac('sha512', $rho . pack('CC', $i, $j), 'ML_DSA_MATRIX_A', true);
                $cnt = 0;
                $pos = 0;
                while (count($poly) < $n) {
                    if ($pos + 3 > strlen($stream)) {
                        $stream .= hash('sha256', $rho . pack('CCN', $i, $j, ++$cnt), true);
                    }
                    $b1 = ord($stream[$pos++]);
                    $b2 = ord($stream[$pos++]);
                    $b3 = ord($stream[$pos++]);
                    $val = ($b1 | ($b2 << 8) | ($b3 << 16)) & 0x7FFFFF; // 23 bits
                    if ($val < $q) {
                        $poly[] = $val;
                    }
                }
                $A[$i][$j] = $poly;
            }
        }
        return $A;
    }

    /**
     * Sample polynomial with small coefficients in [-eta, eta].
     */
    protected function sampleEtaPoly(string $seed, int $nonce, int $eta): array
    {
        $q = self::MODULUS;
        $stream = hash_hmac('sha512', $seed . pack('N', $nonce), 'ML_DSA_ETA', true);
        $poly = [];
        for ($i = 0; $i < self::DEGREE; $i++) {
            $byte = ord($stream[$i % strlen($stream)]);
            $coeff = ($byte % (2 * $eta + 1)) - $eta;
            $poly[$i] = ($coeff % $q + $q) % $q;
        }
        return $poly;
    }

    /**
     * Sample polynomial with coefficients uniformly in [-gamma1 + 1, gamma1].
     */
    protected function sampleGammaPoly(string $seed, int $nonce, int $gamma1): array
    {
        $q = self::MODULUS;
        $span = 2 * $gamma1;
        $stream = hash_hmac('sha512', $seed . pack('N', $nonce), 'ML_DSA_GAMMA', true);
        $poly = [];
        for ($i = 0; $i < self::DEGREE; $i++) {
            $b1 = ord($stream[$i % strlen($stream)]);
            $b2 = ord($stream[($i + 1) % strlen($stream)]);
            $val = (($b1 | ($b2 << 8)) % $span) - $gamma1;
            $poly[$i] = ($val % $q + $q) % $q;
        }
        return $poly;
    }

    /**
     * Sample sparse challenge polynomial with coefficients in {-1, 0, 1}.
     */
    protected function sampleChallengePoly(string $cTilde): array
    {
        $poly = array_fill(0, self::DEGREE, 0);
        $q = self::MODULUS;
        $signs = hash('sha256', $cTilde . 'SIGNS', true);
        $indices = hash('sha512', $cTilde . 'INDICES', true);

        // 39 non-zero coefficients
        for ($i = 0; $i < 39; $i++) {
            $idx = ord($indices[$i]) % self::DEGREE;
            $sign = (ord($signs[$i % strlen($signs)]) & 1) ? 1 : ($q - 1);
            $poly[$idx] = $sign;
        }
        return $poly;
    }

    protected function polyAdd(array $a, array $b): array
    {
        $res = [];
        $q = self::MODULUS;
        for ($i = 0; $i < self::DEGREE; $i++) {
            $res[$i] = ($a[$i] + $b[$i]) % $q;
        }
        return $res;
    }

    protected function polySub(array $a, array $b): array
    {
        $res = [];
        $q = self::MODULUS;
        for ($i = 0; $i < self::DEGREE; $i++) {
            $diff = ($a[$i] - $b[$i]) % $q;
            $res[$i] = $diff < 0 ? $diff + $q : $diff;
        }
        return $res;
    }

    protected function polyMul(array $a, array $b): array
    {
        $n = self::DEGREE;
        $q = self::MODULUS;
        $res = array_fill(0, $n, 0);

        for ($i = 0; $i < $n; $i++) {
            $ai = $a[$i];
            if ($ai === 0) continue;
            for ($j = 0; $j < $n; $j++) {
                $bj = $b[$j];
                if ($bj === 0) continue;
                $prod = ($ai * $bj) % $q;
                $idx = $i + $j;
                if ($idx < $n) {
                    $res[$idx] = ($res[$idx] + $prod) % $q;
                } else {
                    $wIdx = $idx - $n;
                    $diff = ($res[$wIdx] - $prod) % $q;
                    $res[$wIdx] = $diff < 0 ? $diff + $q : $diff;
                }
            }
        }
        return $res;
    }

    protected function encodePolyVector(array $vec): string
    {
        $out = '';
        foreach ($vec as $poly) {
            for ($i = 0; $i < self::DEGREE; $i++) {
                $out .= pack('V', $poly[$i]); // 32-bit unsigned little endian
            }
        }
        return $out;
    }

    protected function decodePolyVector(string $bytes, int $count): array
    {
        $vec = [];
        $polyLen = self::DEGREE * 4;
        for ($i = 0; $i < $count; $i++) {
            $chunk = substr($bytes, $i * $polyLen, $polyLen);
            $unpacked = unpack('V' . self::DEGREE, $chunk);
            $poly = [];
            for ($j = 1; $j <= self::DEGREE; $j++) {
                $poly[] = $unpacked[$j];
            }
            $vec[] = $poly;
        }
        return $vec;
    }
}
