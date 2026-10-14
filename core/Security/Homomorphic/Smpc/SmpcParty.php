<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Smpc/SmpcParty.php
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

use NetFusion\Core\Security\Homomorphic\Math\ModMath;

/**
 * Node/Party in a Secure Multi-Party Computation mesh.
 * Holds partial secret shares and performs local linear additions/multiplications.
 */
class SmpcParty
{
    /** @var array<string, SecretShare> */
    private array $shares = [];

    public function __construct(
        private string $partyId,
        private int $partyIndex
    ) {
    }

    public function getPartyId(): string
    {
        return $this->partyId;
    }

    public function getPartyIndex(): int
    {
        return $this->partyIndex;
    }

    public function setShare(string $variableName, SecretShare $share): self
    {
        $this->shares[$variableName] = $share;
        return $this;
    }

    public function getShare(string $variableName): ?SecretShare
    {
        return $this->shares[$variableName] ?? null;
    }

    /**
     * Local Homomorphic Addition of shares: share_res = (x, (y_a + y_b) mod p).
     */
    public function localAdd(string $varA, string $varB, string $outVar): SecretShare
    {
        $shA = $this->shares[$varA];
        $shB = $this->shares[$varB];

        $yRes = ModMath::add($shA->getY(), $shB->getY(), $shA->getPrime());
        $resShare = new SecretShare($shA->getX(), $yRes, $shA->getPrime());

        $this->shares[$outVar] = $resShare;
        return $resShare;
    }

    /**
     * Local Homomorphic Scalar Multiplication: share_res = (x, (y_a * scalar) mod p).
     */
    public function localScalarMul(string $varA, int $scalar, string $outVar): SecretShare
    {
        $shA = $this->shares[$varA];
        $yRes = ModMath::mul($shA->getY(), $scalar, $shA->getPrime());
        $resShare = new SecretShare($shA->getX(), $yRes, $shA->getPrime());

        $this->shares[$outVar] = $resShare;
        return $resShare;
    }
}
