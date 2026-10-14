<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Protocols/BB84Protocol.php
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

use NetFusion\Core\Security\Quantum\Enums\PhotonState;
use NetFusion\Core\Security\Quantum\Enums\QubitBasis;

/**
 * BB84 Quantum Key Distribution Protocol Simulator (Bennett & Brassard 1984).
 */
class BB84Protocol
{
    /**
     * Simulate complete BB84 transmission between Alice and Bob.
     *
     * @param int $photonCount Number of photons transmitted
     * @param float $eavesdropRate Probability of Eve intercepting each photon (0.0 to 1.0)
     * @param float $noiseRate Background quantum channel noise rate
     * @return array{
     *   alice_bits: int[],
     *   alice_bases: QubitBasis[],
     *   bob_bits: int[],
     *   bob_bases: QubitBasis[],
     *   eve_interceptions: int
     * }
     */
    public function transmit(
        int $photons = 256,
        float $eavesdropRate = 0.0,
        float $noiseRate = 0.0
    ): array {
        $aliceBits  = [];
        $aliceBases = [];
        $bobBits    = [];
        $bobBases   = [];
        $eveInterceptions = 0;

        $bases = [QubitBasis::RECTILINEAR, QubitBasis::DIAGONAL];

        for ($i = 0; $i < $photons; $i++) {
            // 1. Alice prepares random bit and random basis
            $aBit   = mt_rand(0, 1);
            $aBasis = $bases[array_rand($bases)];
            $photon = PhotonState::fromBitAndBasis($aBit, $aBasis);

            $aliceBits[]  = $aBit;
            $aliceBases[] = $aBasis;

            // 2. Quantum Channel Transmission (Eve interception simulation)
            if ($eavesdropRate > 0.0 && (mt_rand(1, 10000) / 10000) <= $eavesdropRate) {
                $eveInterceptions++;
                $eveBasis = $bases[array_rand($bases)];
                // Eve measures photon in her chosen basis
                if ($eveBasis === $photon->getBasis()) {
                    $eveMeasuredBit = $photon->getBit();
                } else {
                    // Superposition collapse: 50% chance 0, 50% chance 1
                    $eveMeasuredBit = mt_rand(0, 1);
                }
                // Eve sends re-encoded photon in Eve's basis
                $photon = PhotonState::fromBitAndBasis($eveMeasuredBit, $eveBasis);
            }

            // Optional channel noise
            if ($noiseRate > 0.0 && (mt_rand(1, 10000) / 10000) <= $noiseRate) {
                $photon = PhotonState::fromBitAndBasis(mt_rand(0, 1), $bases[array_rand($bases)]);
            }

            // 3. Bob measures in a randomly chosen basis
            $bBasis = $bases[array_rand($bases)];
            if ($bBasis === $photon->getBasis()) {
                $bBit = $photon->getBit();
            } else {
                // Quantum indeterminacy when measuring in non-matching basis
                $bBit = mt_rand(0, 1);
            }

            $bobBits[]  = $bBit;
            $bobBases[] = $bBasis;
        }

        return [
            'alice_bits'        => $aliceBits,
            'alice_bases'       => $aliceBases,
            'bob_bits'          => $bobBits,
            'bob_bases'         => $bobBases,
            'eve_interceptions' => $eveInterceptions,
        ];
    }
}
