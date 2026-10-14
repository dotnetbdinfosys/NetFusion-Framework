<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Processing/QberAnalyzer.php
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

use NetFusion\Core\Security\Quantum\Exceptions\EavesdroppingDetectedException;

/**
 * Quantum Bit Error Rate (QBER) Analyzer & Eavesdropper Detection.
 */
class QberAnalyzer
{
    public function __construct(
        private float $qberThreshold = 0.11 // 11.0% maximum error rate before aborting
    ) {
    }

    /**
     * Estimate QBER by sacrificing a sample fraction of sifted bits and testing for eavesdropping.
     *
     * @param int[] $siftedAlice
     * @param int[] $siftedBob
     * @param float $sampleFraction Fraction of bits sacrificed to estimate QBER (e.g. 0.20 = 20%)
     * @return array{
     *   qber: float,
     *   sampled_count: int,
     *   remaining_alice: int[],
     *   remaining_bob: int[],
     *   is_secure: bool
     * }
     * @throws EavesdroppingDetectedException
     */
    public function analyze(
        array $siftedAlice,
        array $siftedBob,
        float $sampleFraction = 0.20
    ): array {
        $total = min(count($siftedAlice), count($siftedBob));
        if ($total === 0) {
            return [
                'qber'            => 0.0,
                'sampled_count'   => 0,
                'remaining_alice' => [],
                'remaining_bob'   => [],
                'is_secure'       => false,
            ];
        }

        $sampleCount = max(1, (int)round($total * $sampleFraction));
        $indices = range(0, $total - 1);
        shuffle($indices);

        $sampleIndices = array_slice($indices, 0, $sampleCount);
        $sampleLookup = array_flip($sampleIndices);

        $errors = 0;
        foreach ($sampleIndices as $idx) {
            if ($siftedAlice[$idx] !== $siftedBob[$idx]) {
                $errors++;
            }
        }

        $qber = round($errors / $sampleCount, 4);

        // Retain remaining unsacrificed bits for key generation
        $remainingAlice = [];
        $remainingBob   = [];
        for ($i = 0; $i < $total; $i++) {
            if (!isset($sampleLookup[$i])) {
                $remainingAlice[] = $siftedAlice[$i];
                $remainingBob[]   = $siftedBob[$i];
            }
        }

        if ($qber > $this->qberThreshold) {
            $pct = round($qber * 100.0, 2);
            $limitPct = round($this->qberThreshold * 100.0, 2);
            throw new EavesdroppingDetectedException(
                "Critical Quantum Disturbance: QBER ({$pct}%) exceeds security bound ({$limitPct}%). An eavesdropper has intercepted the quantum link."
            );
        }

        return [
            'qber'            => $qber,
            'sampled_count'   => $sampleCount,
            'remaining_alice' => $remainingAlice,
            'remaining_bob'   => $remainingBob,
            'is_secure'       => true,
        ];
    }

    public function getQberThreshold(): float
    {
        return $this->qberThreshold;
    }
}
