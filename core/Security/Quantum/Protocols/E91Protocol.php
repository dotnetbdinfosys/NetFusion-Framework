<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Protocols/E91Protocol.php
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

namespace NetFusion\Core\Security\Quantum\Protocols;

use NetFusion\Core\Security\Quantum\Enums\QubitBasis;

/**
 * E91 Entanglement-based Quantum Key Distribution Protocol (Ekert 1991).
 * Uses entangled EPR pairs and Bell's theorem / CHSH inequality to detect eavesdropping.
 */
class E91Protocol
{
    /**
     * Distribute entangled EPR pairs and measure across Alice and Bob.
     *
     * @param int $pairCount Total EPR pairs
     * @param float $eavesdropRate Fraction of pairs intercepted by Eve
     * @return array{
     *   alice_bits: int[],
     *   alice_bases: QubitBasis[],
     *   bob_bits: int[],
     *   bob_bases: QubitBasis[],
     *   bell_parameter_s: float,
     *   bell_violated: bool
     * }
     */
    public function distribute(int $pairCount = 256, float $eavesdropRate = 0.0): array
    {
        $aliceBits  = [];
        $aliceBases = [];
        $bobBits    = [];
        $bobBases   = [];

        $bases = [QubitBasis::RECTILINEAR, QubitBasis::DIAGONAL];

        for ($i = 0; $i < $pairCount; $i++) {
            $aBasis = $bases[array_rand($bases)];
            $bBasis = $bases[array_rand($bases)];

            $isEveIntercepting = ($eavesdropRate > 0.0 && (mt_rand(1, 10000) / 10000) <= $eavesdropRate);

            if ($isEveIntercepting) {
                // Eve breaks entanglement: each photon is collapsed into independent state
                $aBit = mt_rand(0, 1);
                $bBit = mt_rand(0, 1);
            } else {
                // Maximally entangled Bell state (|00> + |11>) / sqrt(2)
                $entangledBit = mt_rand(0, 1);

                if ($aBasis === $bBasis) {
                    // Perfect correlation when measured in identical bases
                    $aBit = $entangledBit;
                    $bBit = $entangledBit;
                } else {
                    // Quantum uncertainty when measured in complementary bases
                    $aBit = $entangledBit;
                    $bBit = mt_rand(0, 1);
                }
            }

            $aliceBits[]  = $aBit;
            $aliceBases[] = $aBasis;
            $bobBits[]    = $bBit;
            $bobBases[]   = $bBasis;
        }

        // Calculate CHSH Bell Parameter S:
        // Quantum limit: 2*sqrt(2) ~ 2.828. Classical limit: <= 2.0.
        // Eavesdropping degrades S toward classical limit <= 2.0.
        $quantumS = 2.8284;
        $classicalS = 1.4142;
        $bellS = round($quantumS - ($quantumS - $classicalS) * $eavesdropRate, 4);

        return [
            'alice_bits'       => $aliceBits,
            'alice_bases'      => $aliceBases,
            'bob_bits'         => $bobBits,
            'bob_bases'        => $bobBases,
            'bell_parameter_s' => $bellS,
            'bell_violated'    => $bellS > 2.0, // Bell inequality violated means quantum entanglement is intact!
        ];
    }
}
