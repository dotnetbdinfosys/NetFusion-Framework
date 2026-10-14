<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Smpc/ShamirSecretSharing.php
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

namespace NetFusion\Core\Security\Homomorphic\Smpc;

use InvalidArgumentException;
use NetFusion\Core\Security\Homomorphic\Exceptions\ThresholdException;
use NetFusion\Core\Security\Homomorphic\Math\ModMath;

/**
 * Shamir's (k, n) Threshold Secret Sharing Scheme over finite field F_p.
 * Splits a secret into n shares such that any k shares can reconstruct it,
 * while any k - 1 shares reveal zero information.
 */
class ShamirSecretSharing
{
    // Standard 31-bit Mersenne prime: 2^31 - 1
    public const DEFAULT_PRIME = 2147483647;

    public function __construct(private int $prime = self::DEFAULT_PRIME)
    {
    }

    public function getPrime(): int
    {
        return $this->prime;
    }

    /**
     * Split a secret s into n shares with threshold k.
     * Polynomial: f(x) = s + a_1 * x + a_2 * x^2 + ... + a_{k-1} * x^{k-1} mod p
     *
     * @param int $secret Secret integer in [0, p - 1]
     * @param int $threshold k Minimum shares required to reconstruct
     * @param int $totalShares n Total shares to distribute
     * @return SecretShare[]
     */
    public function split(int $secret, int $threshold, int $totalShares): array
    {
        if ($threshold < 2) {
            throw new InvalidArgumentException("Threshold k must be at least 2.");
        }
        if ($totalShares < $threshold) {
            throw new InvalidArgumentException("Total shares n must be >= threshold k.");
        }
        if ($secret < 0 || $secret >= $this->prime) {
            throw new InvalidArgumentException("Secret must be in range [0, prime - 1].");
        }

        // Coefficients: a_0 = secret, a_1 ... a_{k-1} random in [1, prime - 1]
        $coeffs = [$secret];
        for ($i = 1; $i < $threshold; $i++) {
            $coeffs[] = mt_rand(1, $this->prime - 1);
        }

        // Evaluate f(x) for x = 1, ..., n
        $shares = [];
        for ($x = 1; $x <= $totalShares; $x++) {
            $y = 0;
            $xPow = 1;
            foreach ($coeffs as $c) {
                $term = ModMath::mul($c, $xPow, $this->prime);
                $y = ModMath::add($y, $term, $this->prime);
                $xPow = ModMath::mul($xPow, $x, $this->prime);
            }
            $shares[] = new SecretShare($x, $y, $this->prime);
        }

        return $shares;
    }

    /**
     * Reconstruct secret using Lagrange Polynomial Interpolation at x = 0:
     * s = f(0) = sum_{i=0}^{k-1} ( y_i * prod_{j != i} ( (0 - x_j) / (x_i - x_j) ) ) mod p
     *
     * @param SecretShare[] $shares At least k shares
     * @param int $threshold
     * @return int Reconstructed secret
     */
    public function reconstruct(array $shares, int $threshold): int
    {
        if (count($shares) < $threshold) {
            throw new ThresholdException("Insufficient shares provided: " . count($shares) . " (Required: {$threshold})");
        }

        // Take first k shares
        $subset = array_slice($shares, 0, $threshold);
        $secret = 0;

        foreach ($subset as $i => $shareI) {
            $xi = $shareI->getX();
            $yi = $shareI->getY();

            // Compute Lagrange basis polynomial l_i(0)
            $num = 1;
            $den = 1;
            foreach ($subset as $j => $shareJ) {
                if ($i === $j) {
                    continue;
                }
                $xj = $shareJ->getX();

                // (0 - xj) mod p
                $negXj = ModMath::mod(-$xj, $this->prime);
                $num = ModMath::mul($num, $negXj, $this->prime);

                // (xi - xj) mod p
                $diff = ModMath::sub($xi, $xj, $this->prime);
                $den = ModMath::mul($den, $diff, $this->prime);
            }

            $li0 = ModMath::mul($num, ModMath::inverse($den, $this->prime), $this->prime);
            $term = ModMath::mul($yi, $li0, $this->prime);
            $secret = ModMath::add($secret, $term, $this->prime);
        }

        return $secret;
    }
}
