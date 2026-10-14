<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/Channel/QkdChannel.php
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

namespace NetFusion\Core\Security\Quantum\Channel;

use NetFusion\Core\Security\Quantum\Exceptions\QuantumException;

/**
 * Quantum-Secured Communication Channel utilizing QKD-distilled keys.
 * Supports information-theoretically secure One-Time Pad (OTP) and Quantum-Seeded AES-256-GCM.
 */
class QkdChannel
{
    /**
     * Information-Theoretically Secure One-Time Pad (Vernam Cipher) Encryption.
     * C = P ⊕ K
     */
    public function encryptOtp(string $plaintext, string $quantumKeyBinary): array
    {
        $len = strlen($plaintext);
        if (strlen($quantumKeyBinary) < $len) {
            throw new QuantumException("Quantum key stream exhausted: plaintext requires {$len} bytes, but only " . strlen($quantumKeyBinary) . " bytes provided.");
        }

        $keyStream = substr($quantumKeyBinary, 0, $len);
        $ciphertext = $plaintext ^ $keyStream;

        return [
            'ciphertext_hex' => bin2hex($ciphertext),
            'key_used_hex'   => bin2hex($keyStream),
            'bytes_used'     => $len,
        ];
    }

    /**
     * One-Time Pad Decryption.
     * P = C ⊕ K
     */
    public function decryptOtp(string $ciphertextHex, string $keyUsedHex): string
    {
        $ciphertext = hex2bin($ciphertextHex);
        $key        = hex2bin($keyUsedHex);

        if ($ciphertext === false || $key === false) {
            throw new QuantumException("Invalid hex format for ciphertext or key.");
        }

        return $ciphertext ^ $key;
    }

    /**
     * Authenticated Quantum-Keyed AES-256-GCM Encryption.
     */
    public function encryptAesGcm(string $plaintext, string $quantumKeyBinary): array
    {
        $key = hash('sha256', $quantumKeyBinary, true);
        $nonce = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            throw new QuantumException("Failed to encrypt with quantum key: " . openssl_error_string());
        }

        return [
            'ciphertext_hex' => bin2hex($ciphertext),
            'nonce_hex'      => bin2hex($nonce),
            'tag_hex'        => bin2hex($tag),
        ];
    }

    /**
     * Authenticated Quantum-Keyed AES-256-GCM Decryption.
     */
    public function decryptAesGcm(
        string $ciphertextHex,
        string $quantumKeyBinary,
        string $nonceHex,
        string $tagHex
    ): string {
        $key = hash('sha256', $quantumKeyBinary, true);
        $ciphertext = hex2bin($ciphertextHex);
        $nonce      = hex2bin($nonceHex);
        $tag        = hex2bin($tagHex);

        if ($ciphertext === false || $nonce === false || $tag === false) {
            throw new QuantumException("Corrupted hex payload in quantum decryption.");
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new QuantumException("Quantum message authentication failed: payload tampered or key mismatch.");
        }

        return $plaintext;
    }
}
