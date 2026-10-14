<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Homomorphic/Engine/EncryptedComputeEngine.php
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

namespace NetFusion\Core\Security\Homomorphic\Engine;

use NetFusion\Core\Security\Homomorphic\Schemes\BfvEngine;
use NetFusion\Core\Security\Homomorphic\Schemes\Ciphertext;
use NetFusion\Core\Security\Homomorphic\Schemes\CkksEngine;
use NetFusion\Core\Security\Homomorphic\Schemes\KeyPair;

/**
 * High-Level Encrypted Computation Engine.
 * Evaluates linear vector combinations and weighted sum aggregations without decrypting.
 */
class EncryptedComputeEngine
{
    public function __construct(
        private BfvEngine $bfv,
        private CkksEngine $ckks
    ) {
    }

    /**
     * Compute encrypted integer vector sum: sum_{i} c_i.
     *
     * @param Ciphertext[] $ciphertexts
     */
    public function sumInteger(array $ciphertexts): ?Ciphertext
    {
        if (empty($ciphertexts)) {
            return null;
        }

        $accum = $ciphertexts[0];
        $count = count($ciphertexts);
        for ($i = 1; $i < $count; $i++) {
            $accum = $this->bfv->add($accum, $ciphertexts[$i]);
        }
        return $accum;
    }

    /**
     * Compute encrypted integer weighted inner product: sum_{i} (w_i * c_i).
     *
     * @param Ciphertext[] $ciphertexts
     * @param int[] $weights Plaintext integer weights
     */
    public function innerProductInteger(array $ciphertexts, array $weights): ?Ciphertext
    {
        if (empty($ciphertexts) || empty($weights)) {
            return null;
        }

        $res = null;
        foreach ($ciphertexts as $idx => $cipher) {
            $w = $weights[$idx] ?? 0;
            if ($w === 0) {
                continue;
            }
            $term = $this->bfv->multiplyPlain($cipher, $w);
            $res = ($res === null) ? $term : $this->bfv->add($res, $term);
        }

        return $res;
    }

    /**
     * Compute encrypted approximate floating vector sum.
     *
     * @param Ciphertext[] $ciphertexts
     */
    public function sumFloat(array $ciphertexts): ?Ciphertext
    {
        if (empty($ciphertexts)) {
            return null;
        }

        $accum = $ciphertexts[0];
        $count = count($ciphertexts);
        for ($i = 1; $i < $count; $i++) {
            $accum = $this->ckks->add($accum, $ciphertexts[$i]);
        }
        return $accum;
    }
}
