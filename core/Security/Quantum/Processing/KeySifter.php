<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Processing/KeySifter.php
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

namespace NetFusion\Core\Security\Quantum\Processing;

use NetFusion\Core\Security\Quantum\Enums\QubitBasis;

/**
 * Quantum Key Sifter matching Alice and Bob's measurement bases.
 */
class KeySifter
{
    /**
     * Sift transmitted raw bits by matching classical basis announcements.
     *
     * @param int[] $aliceBits
     * @param QubitBasis[] $aliceBases
     * @param int[] $bobBits
     * @param QubitBasis[] $bobBases
     * @return array{
     *   sifted_alice: int[],
     *   sifted_bob: int[],
     *   matching_indices: int[],
     *   sifting_efficiency: float
     * }
     */
    public function sift(
        array $aliceBits,
        array $aliceBases,
        array $bobBits,
        array $bobBases
    ): array {
        $n = min(count($aliceBits), count($aliceBases), count($bobBits), count($bobBases));

        $siftedAlice = [];
        $siftedBob   = [];
        $matchingIdx = [];

        for ($i = 0; $i < $n; $i++) {
            if ($aliceBases[$i]->isCompatible($bobBases[$i])) {
                $siftedAlice[] = $aliceBits[$i];
                $siftedBob[]   = $bobBits[$i];
                $matchingIdx[] = $i;
            }
        }

        $efficiency = $n > 0 ? round((count($siftedAlice) / $n) * 100.0, 2) : 0.0;

        return [
            'sifted_alice'       => $siftedAlice,
            'sifted_bob'         => $siftedBob,
            'matching_indices'   => $matchingIdx,
            'sifting_efficiency' => $efficiency,
        ];
    }
}
