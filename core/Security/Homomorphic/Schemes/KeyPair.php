<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Schemes/KeyPair.php
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

namespace NetFusion\Core\Security\Homomorphic\Schemes;

/**
 * KeyPair holding Secret Key s, Public Key pk = (p0, p1), and Relinearization Key rlk.
 */
class KeyPair
{
    /**
     * @param int[] $secretKey Polynomial s in R_q
     * @param array{0: int[], 1: int[]} $publicKey (p0, p1) where p0 = -(a*s + e), p1 = a
     * @param array|null $relinearizationKey
     */
    public function __construct(
        private array $secretKey,
        private array $publicKey,
        private ?array $relinearizationKey = null
    ) {
    }

    /**
     * @return int[]
     */
    public function getSecretKey(): array
    {
        return $this->secretKey;
    }

    /**
     * @return array{0: int[], 1: int[]}
     */
    public function getPublicKey(): array
    {
        return $this->publicKey;
    }

    public function getRelinearizationKey(): ?array
    {
        return $this->relinearizationKey;
    }
}
