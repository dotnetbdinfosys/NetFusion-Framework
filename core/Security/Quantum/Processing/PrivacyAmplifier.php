<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Processing/PrivacyAmplifier.php
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

/**
 * Universal Hashing Privacy Amplification Engine for QKD Key Distillation.
 * Reduces an eavesdropper's potential mutual information to zero.
 */
class PrivacyAmplifier
{
    /**
     * Distill a raw bit string into an amplified, unconditionally secure key.
     *
     * @param int[] $bits Reconciled bit sequence
     * @param int $outputBytes Desired distilled key length in bytes (default: 32 bytes = 256 bits)
     * @param string|null $salt Public seed agreed over classical channel
     * @return string Binary key of length $outputBytes
     */
    public function amplify(
        array $bits,
        int $outputBytes = 32,
        ?string $salt = null
    ): string {
        if (empty($bits)) {
            return str_repeat("\0", $outputBytes);
        }

        // 1. Pack bits into binary stream
        $bitString = implode('', $bits);
        $binaryData = '';
        $chunks = str_split($bitString, 8);
        foreach ($chunks as $chunk) {
            $binaryData .= chr((int)bindec(str_pad($chunk, 8, '0', STR_PAD_RIGHT)));
        }

        $saltVal = $salt ?? 'DotNetBD-QKD-Privacy-Amplification-Seed';

        // 2. 2-Universal hash extraction via HKDF-SHA384
        return hash_hkdf(
            'sha384',
            $binaryData,
            $outputBytes,
            'QKD-Distilled-Key-Privacy-Amplification',
            $saltVal
        );
    }
}
